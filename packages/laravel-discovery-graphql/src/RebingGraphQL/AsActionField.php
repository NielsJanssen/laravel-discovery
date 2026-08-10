<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Foundation\Application;
use Illuminate\Validation\Rule;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Field;
use ReflectionMethod;
use RuntimeException;

/**
 * @phpstan-require-extends Field
 */
trait AsActionField
{
    /** @var \ReflectionParameter[] */
    private array $reflectionParameters;

    private ?Authorize $failedAuthorize = null;

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

        foreach ($this->discoveredAction->args as $arg) {
            $graphqlType = $this->scalarType($arg->type);

            if (! $arg->nullable) {
                $graphqlType = GraphQLType::nonNull($graphqlType);
            }

            $entry = ['type' => $graphqlType];

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
            $graphqlType = $binding->type === null
                ? GraphQLType::id()
                : $this->scalarType($binding->type);

            if (! $binding->nullable) {
                $graphqlType = GraphQLType::nonNull($graphqlType);
            }

            $entry = ['type' => $graphqlType];

            $rules = $this->resolveModelBindingRules($binding);

            if ($rules instanceof Closure || $rules !== []) {
                $entry['rules'] = $rules;
            }

            $args[$binding->argName] = $entry;
        }

        foreach ($this->discoveredAction->argProviders as $provider) {
            foreach ($provider->provideArgs() as $name => $def) {
                $args[$name] = $def;
            }
        }

        return $args;
    }

    public function type(): GraphQLType
    {
        $action = $this->discoveredAction->action;

        if ($this->discoveredAction->typeBuilder !== null) {
            return $this->discoveredAction->typeBuilder->buildType($action);
        }

        $innerType = match ($action->type) {
            'void' => new NullType(),
            null => throw new RuntimeException('Action type was not resolved during discovery.'),
            default => $this->scalarType($action->type),
        };

        if ($action->list) {
            $type = GraphQLType::listOf(GraphQLType::nonNull($innerType));
        } else {
            $type = $innerType;
        }

        return $action->nullable ? $type : GraphQLType::nonNull($type);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function resolve(mixed $root, array $args, mixed $context, ?ResolveInfo $info): mixed
    {
        $mappedArgs = [];

        foreach ($this->discoveredAction->args as $discovered) {
            $value = $args[$discovered->name] ?? null;
            $mappedArgs[$discovered->paramName] = $value ?? $discovered->defaultValue;
        }

        $hydrators = $this->app->make(ArgumentHydrators::class);

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

        foreach ($this->discoveredAction->modelBindings as $binding) {
            $value = $args[$binding->argName] ?? null;

            if ($value === null) {
                $mappedArgs[$binding->paramName] = null;

                continue;
            }

            $modelClass = $binding->modelClass;
            $key = new $modelClass()->getRouteKeyName();
            $query = $modelClass::query()->where($key, $value);

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
     * Merge the registered ArgumentRules providers on top of Rebing's own arg-level rules.
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
        return [...parent::validationErrorMessages($args), ...$this->argumentRules($args)->messages];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function argumentRules(array $args): ArgumentRuleSet
    {
        return $this->app->make(ArgumentRuleProviders::class)->rulesFor($this->discoveredAction, $args);
    }

    public function authorize(mixed $root, array $args, mixed $context, ?ResolveInfo $resolveInfo = null): bool
    {
        foreach ($this->discoveredAction->authorizations as $auth) {
            if ($auth->gate) {
                /** @var AuthorizationGate $gate */
                $gate = $this->app->make($auth->gate);

                $passed = $gate->check($root, $args, $context, $resolveInfo);
            } else {
                $passed = auth()->check();
            }

            if (! $passed) {
                $this->failedAuthorize = $auth;

                return false;
            }
        }

        return true;
    }

    public function getAuthorizationMessage(): string
    {
        return $this->failedAuthorize->message ?? parent::getAuthorizationMessage();
    }

    /**
     * Map a scalar type name to its GraphQL type, falling back to the Rebing
     * type registry for anything non-scalar.
     *
     * @return (NullableType&GraphQLType)|NonNull
     */
    private function scalarType(string $type): GraphQLType
    {
        return match ($type) {
            'string' => GraphQLType::string(),
            'int' => GraphQLType::int(),
            'float' => GraphQLType::float(),
            'bool' => GraphQLType::boolean(),
            default => GraphQL::type($type),
        };
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
