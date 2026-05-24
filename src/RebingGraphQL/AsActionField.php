<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

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

    public function __construct(
        private readonly Application $app,
        private readonly DiscoveredAction $discoveredAction,
    ) {}

    public function attributes(): array
    {
        return [
            'name' => $this->discoveredAction->action->name ?? $this->discoveredAction->method,
        ];
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
                $entry['rules'] = $this->resolveClosureRules($arg->paramName);
            }

            $args[$arg->name] = $entry;
        }

        return $args;
    }

    public function type(): GraphQLType
    {
        $action = $this->discoveredAction->action;
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

    public function resolve($root, array $args): mixed
    {
        $mappedArgs = [];

        foreach ($this->discoveredAction->args as $discovered) {
            $value = $args[$discovered->name] ?? null;
            $mappedArgs[$discovered->paramName] = $value ?? $discovered->defaultValue;
        }

        $class = $this->discoveredAction->class;
        $method = $this->discoveredAction->method;

        return $this->app->make($class)->$method(...$mappedArgs);
    }

    private function resolveClosureRules(string $paramName): \Closure
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
