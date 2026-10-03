<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Validation\Rule;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ArgumentRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use Rebing\GraphQL\Support\Field as RebingField;
use ReflectionMethod;
use RuntimeException;

/**
 * @phpstan-require-extends RebingField
 */
trait AsActionField
{
    /** @var \ReflectionParameter[] */
    private array $reflectionParameters;

    private ?Authorize $failedAuthorize = null;

    /** Whether the failed check guarded a bound model, which defaults to Authorize::DEFAULT_MESSAGE. */
    private bool $failedOnBoundModel = false;

    public function __construct(
        private readonly Application $app,
        private readonly DiscoveredAction $discoveredAction,
    ) {}

    public function attributes(): array
    {
        $action = $this->discoveredAction->action;

        $attrs = [
            'name' => $action->name ?? $this->discoveredAction->method,
        ];

        if ($action->description !== null) {
            $attrs['description'] = $action->description;
        }

        if ($this->discoveredAction->deprecationReason !== null) {
            $attrs['deprecationReason'] = $this->discoveredAction->deprecationReason;
        }

        return $attrs;
    }

    public function args(): array
    {
        $args = [];
        $registry = $this->app->make(TypeRegistry::class);

        foreach ($this->discoveredAction->args as $arg) {
            $entry = ['type' => $registry->resolve($arg->ref(), Position::Input)];

            if ($arg->description !== null) {
                $entry['description'] = $arg->description;
            }

            if ($arg->hasRules) {
                $entry['rules'] = $this->resolveRules($arg->paramName);
            }

            if ($arg->deprecationReason !== null) {
                $entry['deprecationReason'] = $arg->deprecationReason;
            }

            $args[$arg->name] = $entry;
        }

        foreach ($this->discoveredAction->modelBindings as $binding) {
            $entry = ['type' => $registry->resolve(TypeRef::from($binding->type ?? 'ID', nullable: $binding->nullable), Position::Input)];

            $rules = $this->resolveModelBindingRules($binding);

            if ($rules instanceof Closure || $rules !== []) {
                $entry['rules'] = $rules;
            }

            $args[$binding->argName] = $entry;
        }

        foreach ($this->discoveredAction->flattenedInputs as $flattened) {
            foreach ($flattened->type->fields as $field) {
                $args[$field->name] = $this->flattenedArg($registry, $flattened, $field);
            }
        }

        foreach ($this->discoveredAction->argProviders as $provider) {
            foreach ($provider->provideArgs() as $name => $def) {
                $args[$name] = $def;
            }
        }

        return $args;
    }

    /**
     * A field of an #[AsArgs] input as a top-level arg, defined as on the input type but without an alias.
     *
     * @return array<string, mixed>
     */
    private function flattenedArg(TypeRegistry $registry, DiscoveredFlattenedInput $flattened, DiscoveredTypeField $field): array
    {
        $rules = $field->hasRules || ($field->binding !== null && ! $field->binding->nullable)
            ? static fn(array $args, array $request = []): array => DiscoveredInputType::fieldRules(
                $flattened->type->class,
                $field,
                $flattened->ownArgs($args),
                array_filter($request, is_string(...), ARRAY_FILTER_USE_KEY),
            )
            : null;

        return DiscoveredInputType::fieldDefinition($registry, $field, $rules, alias: false);
    }

    public function type(): GraphQLType
    {
        $action = $this->discoveredAction->action;
        $ref = $this->discoveredAction->returnType;
        $registry = $this->app->make(TypeRegistry::class);

        if ($this->discoveredAction->typeBuilder !== null) {
            $resolved = $ref === null ? $action : clone($action, ['type' => $registry->name($ref, Position::Output)]);

            $type = $this->discoveredAction->typeBuilder->buildType($resolved);

            return ! $action->nullable && $type instanceof NullableType ? GraphQLType::nonNull($type) : $type;
        }

        if ($ref === null) {
            throw new RuntimeException('Action type was not resolved during discovery.');
        }

        return $registry->resolve($ref, Position::Output);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function resolve(mixed $root, array $args, mixed $context, ?ResolveInfo $info): mixed
    {
        $mappedArgs = [];

        $hydrators = $this->app->make(HydratorRegistry::class);

        foreach ($this->discoveredAction->args as $discovered) {
            $value = $args[$discovered->name] ?? null;

            if ($discovered->input && is_array($value) && class_exists($discovered->type)) {
                $value = $hydrators->hydrate($discovered->type, array_filter($value, is_string(...), ARRAY_FILTER_USE_KEY));
            }

            $mappedArgs[$discovered->paramName] = $value ?? $discovered->defaultValue;
        }

        foreach ($this->discoveredAction->injections as $paramName => $kind) {
            $mappedArgs[$paramName] = match ($kind) {
                'root' => $root,
                'context' => $context,
                'info' => $info,
            };
        }

        foreach ($this->discoveredAction->argCompositions as $paramName => $valueObjectClass) {
            $mappedArgs[$paramName] = $hydrators->hydrate($valueObjectClass, $args);
        }

        foreach ($this->discoveredAction->flattenedInputs as $flattened) {
            $mappedArgs[$flattened->paramName] = $hydrators->hydrate($flattened->type->class, $flattened->toProperties($args));
        }

        foreach ($this->discoveredAction->modelBindings as $binding) {
            $value = $args[$binding->argName] ?? null;

            if ($value === null) {
                $mappedArgs[$binding->paramName] = null;

                continue;
            }

            $query = $binding->query($value);

            $mappedArgs[$binding->paramName] = $binding->nullable
                ? $query->first()
                : $query->firstOrFail();
        }

        return $this->app->call(
            $this->discoveredAction->class . '@' . $this->discoveredAction->method,
            $mappedArgs,
        );
    }

    protected function getMiddleware(): array
    {
        return $this->discoveredAction->middleware;
    }

    /**
     * Merge the registered rule providers on top of Rebing's own arg-level rules.
     *
     * Appending after parent::getRules() rather than overriding rules() is deliberate: Field's
     * getRules() does array_merge($argsRules, $rules), so anything returned from rules() would
     * *replace* the entry for the same arg — silently dropping #[Arg(rules:)] and the model-binding
     * `exists` rule. This way the parent has already resolved arg-level Closures and applied its
     * RulesPrefixer pass, and we add to the result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function getRules(array $arguments = []): array
    {
        $rules = parent::getRules($arguments);

        foreach ($this->argumentRules($arguments)->rules as $path => $contributed) {
            $existing = $rules[$path] ?? [];

            $rules[$path] = [
                ...is_array($existing) ? $existing : [$existing],
                ...is_array($contributed) ? $contributed : [$contributed],
            ];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    public function validationErrorMessages(array $args = []): array
    {
        return [...parent::validationErrorMessages($args), ...$this->argumentRules($args)->messages, ...$this->inputMessages($args)];
    }

    /**
     * The messages rule providers give an input object's properties, keyed by their full path, e.g. `input.title.min`.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    private function inputMessages(array $args): array
    {
        $providers = $this->app->make(RuleProviderRegistry::class);
        $messages = [];

        foreach ($this->app->make(InputObjects::class)->inArgs($this->args(), $args) as [$type, $values, $path]) {
            $names = array_column(array_map(static fn(DiscoveredTypeField $field): array => [$field->phpName, $field->name], $type->fields), 1, 0);

            foreach ($providers->rulesForInput($type->class, $type->toProperties($values))->messages as $key => $message) {
                $parts = explode('.', $key, 2);
                $suffix = $parts[1] ?? null;
                $field = $names[$parts[0]] ?? $parts[0];

                $messages[$suffix === null ? "$path.$field" : "$path.$field.$suffix"] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function argumentRules(array $args): ArgumentRules
    {
        return $this->app->make(RuleProviderRegistry::class)->rulesFor($this->discoveredAction, $args);
    }

    public function authorize(mixed $root, array $args, mixed $context, ?ResolveInfo $resolveInfo = null): bool
    {
        $this->failedOnBoundModel = false;

        foreach ($this->discoveredAction->authorizations as $auth) {
            if (! $auth->allows($this->app, $root, $args, $context, $resolveInfo)) {
                $this->failedAuthorize = $auth;

                return false;
            }
        }

        return $this->authorizeBoundModels($args, $context, $resolveInfo);
    }

    /**
     * #[Authorize('ability')] on a model-bound parameter or input property, checked against the record it binds.
     *
     * @param  array<string, mixed>  $args
     */
    private function authorizeBoundModels(array $args, mixed $context, ?ResolveInfo $resolveInfo): bool
    {
        $denied = $this->deniedBoundModel($args, $context, $resolveInfo);

        if ($denied === null) {
            return true;
        }

        $this->failedAuthorize = $denied;
        $this->failedOnBoundModel = true;

        return false;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function deniedBoundModel(array $args, mixed $context, ?ResolveInfo $resolveInfo): ?Authorize
    {
        foreach ($this->discoveredAction->modelBindings as $binding) {
            $denied = $binding->deniedBy($this->app, $args[$binding->argName] ?? null, $args, $context, $resolveInfo);

            if ($denied !== null) {
                return $denied;
            }
        }

        foreach ($this->discoveredAction->flattenedInputs as $flattened) {
            foreach ($flattened->type->fields as $field) {
                $denied = $field->binding?->deniedBy($this->app, $args[$field->name] ?? null, $args, $context, $resolveInfo);

                if ($denied !== null) {
                    return $denied;
                }
            }
        }

        return $this->app->make(InputAuthorization::class)->deniedBy($this->args(), $args, $context, $resolveInfo);
    }

    public function getAuthorizationMessage(): string
    {
        return $this->failedAuthorize->message
            ?? ($this->failedOnBoundModel ? Authorize::DEFAULT_MESSAGE : parent::getAuthorizationMessage());
    }

    /**
     * @return array<int|string, mixed>|Closure
     */
    private function resolveRules(string $paramName): array|Closure
    {
        $class = $this->discoveredAction->class;
        $method = $this->discoveredAction->method;

        $this->reflectionParameters ??= new ReflectionMethod($class, $method)->getParameters();

        foreach ($this->reflectionParameters as $param) {
            if ($param->getName() === $paramName) {
                $attrs = $param->getAttributes(Arg::class);

                if (!empty($attrs) && $rules = $attrs[0]->newInstance()->rules) {
                    return $rules;
                }
            }
        }

        throw new RuntimeException("Could not find #[Arg] on parameter \$$paramName in $class::$method.");
    }

    /**
     * Build the validation rules for a model binding: an auto `exists` rule for
     * non-nullable bindings, merged with any user-supplied #[Arg(rules:)].
     *
     * @return array<int|string, mixed>|Closure
     */
    private function resolveModelBindingRules(DiscoveredModelBinding $binding): array|Closure
    {
        $rules = [];

        if (! $binding->nullable) {
            $model = new $binding->modelClass();
            $rules[] = Rule::exists($model->getTable(), $model->getRouteKeyName());
        }

        if (! $binding->hasUserRules) {
            return $rules;
        }

        $userRules = $this->resolveRules($binding->paramName);

        if ($userRules instanceof Closure) {
            return $userRules;
        }

        return array_merge($rules, $userRules);
    }
}
