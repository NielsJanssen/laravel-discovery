<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GraphQLType;
use Illuminate\Foundation\Application;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Field;

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
            $graphqlType = match ($arg->type) {
                'string' => GraphQLType::string(),
                'int' => GraphQLType::int(),
                'float' => GraphQLType::float(),
                'bool' => GraphQLType::boolean(),
                default => GraphQL::type($arg->type),
            };

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

        return $args;
    }

    public function type(): GraphQLType
    {
        $action = $this->discoveredAction->action;

        if ($this->discoveredAction->typeBuilder !== null) {
            return $this->discoveredAction->typeBuilder->buildType($action);
        }

        $innerType = match ($action->type) {
            'string' => GraphQLType::string(),
            'int' => GraphQLType::int(),
            'float' => GraphQLType::float(),
            'bool' => GraphQLType::boolean(),
            'void' => new NullType(),
            default => GraphQL::type($action->type),
        };

        if ($action->list) {
            $type = GraphQLType::listOf(GraphQLType::nonNull($innerType));
        } else {
            $type = $innerType;
        }

        return $action->nullable ? $type : GraphQLType::nonNull($type);
    }

    public function resolve(mixed $root, array $args, mixed $context, ?ResolveInfo $info): mixed
    {
        $mappedArgs = [];

        foreach ($this->discoveredAction->args as $discovered) {
            $value = $args[$discovered->name] ?? null;
            $mappedArgs[$discovered->paramName] = $value ?? $discovered->defaultValue;
        }

        foreach ($this->discoveredAction->injections as $paramName => $kind) {
            $mappedArgs[$paramName] = match ($kind) {
                'root' => $root,
                'context' => $context,
                'info' => $info,
            };
        }

        foreach ($this->discoveredAction->containerInjections as $paramName => $fqcn) {
            $mappedArgs[$paramName] = $this->app->make($fqcn);
        }

        return $this->app->make($this->discoveredAction->class)
            ->{$this->discoveredAction->method}(...$mappedArgs);
    }

    protected function getMiddleware(): array
    {
        return $this->discoveredAction->middleware;
    }

    public function authorize(mixed $root, array $args, mixed $context, ?ResolveInfo $resolveInfo = null): bool
    {
        foreach ($this->discoveredAction->authorizations as $auth) {
            $passed = $auth->gate !== null
                ? $this->app->make($auth->gate)->check($root, $args, $context, $resolveInfo)
                : auth()->check();

            if (! $passed) {
                $this->failedAuthorize = $auth;

                return false;
            }
        }

        return true;
    }

    public function getAuthorizationMessage(): string
    {
        return $this->failedAuthorize?->message ?? parent::getAuthorizationMessage();
    }

    private function resolveRules(string $paramName): array|\Closure
    {
        $class = $this->discoveredAction->class;
        $method = $this->discoveredAction->method;

        $this->reflectionParameters ??= new \ReflectionMethod($class, $method)->getParameters();

        foreach ($this->reflectionParameters as $param) {
            if ($param->getName() === $paramName) {
                $attrs = $param->getAttributes(Arg::class);

                if (! empty($attrs)) {
                    return $attrs[0]->newInstance()->rules;
                }
            }
        }

        throw new \RuntimeException("Could not find #[Arg] on parameter \${$paramName} in {$class}::{$method}.");
    }
}
