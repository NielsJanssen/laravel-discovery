<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\Support\Mutation as RebingMutation;
use Rebing\GraphQL\Support\Query as RebingQuery;
use Rebing\GraphQL\Support\Type as RebingType;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

class GraphQLDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!class_exists(GraphQL::class) || ! $class->isInstantiable()) {
            return;
        }

        if ($class->is(RebingType::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('types', $class->getName()));

            return;
        }

        if ($class->is(RebingQuery::class) && ! $class->is(QueryField::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('query', $class->getName()));

            return;
        }

        if ($class->is(RebingMutation::class) && ! $class->is(MutationField::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('mutation', $class->getName()));

            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            $action = $method->getAttribute(Query::class) ?? $method->getAttribute(Mutation::class);

            if (! $action) {
                continue;
            }

            if ($action->type === null) {
                $returnType = $method->getReturnType();
                $phpType = $returnType?->getName();

                if ($phpType === 'void') {
                    $action->type = 'void';
                    $action->nullable = true;
                } elseif ($returnType !== null && $returnType->isScalar()) {
                    $action->type = $phpType;

                    if ($returnType->isNullable()) {
                        $action->nullable = true;
                    }
                } else {
                    throw new \RuntimeException(sprintf(
                        'Method %s::%s has no scalar return type. Specify type: in #[%s], or use a scalar or void return type hint.',
                        $class->getName(),
                        $method->getName(),
                        class_basename($action::class),
                    ));
                }
            }

            $args = [];
            foreach ($method->getParameters() as $param) {
                $typeReflector = $param->getType();

                /** @var Arg|null $argAttr */
                $argAttr = $param->getAttribute(Arg::class);

                if ($argAttr?->type !== null) {
                    $typeName = $argAttr->type;
                } elseif ($typeReflector->isScalar()) {
                    $typeName = $typeReflector->getName();
                } else {
                    throw new \RuntimeException(sprintf(
                        'Parameter $%s in %s::%s is not a scalar type. Use #[Arg(type: \'GraphQLTypeName\')] to specify the GraphQL type.',
                        $param->getName(),
                        $class->getName(),
                        $method->getName(),
                    ));
                }

                $hasRules = !empty($argAttr?->rules);
                $hasDefault = $param->hasDefaultValue();

                $args[] = new DiscoveredArg(
                    name: $argAttr?->name ?? $param->getName(),
                    paramName: $param->getName(),
                    type: $typeName,
                    nullable: $typeReflector->isNullable() || $hasDefault,
                    description: $argAttr?->description,
                    hasRules: $hasRules,
                    hasDefault: $hasDefault,
                    defaultValue: $hasDefault ? $param->getDefaultValue() : null,
                );
            }

            $this->discoveryItems->add($location, new DiscoveredAction($action, $class->getName(), $method->getName(), $args));
        }
    }

    public function apply(): void
    {
        $buildConfig = !$this->app->configurationIsCached();
        $schemas = [];

        foreach ($this->discoveryItems as $item) {
            $schema = 'default';

            if ($item instanceof DiscoveredAction) {
                $bindName = 'discovery.rebing_graphql.' . hash('sha256', serialize($item));

                $this->app->singleton($bindName, $item->createType(...));

                if ($buildConfig) {
                    $schemas[$schema][$item->fieldType][$item->action->name] = $bindName;
                }
            } elseif ($item instanceof DiscoveredField && $buildConfig) {
                $schemas[$schema][$item->fieldType][$item->getName()] = $item->class;
            }
        }

        if ($buildConfig && !empty($schemas)) {
            $config = $this->app->make('config');
            $config->set('graphql.schemas', array_merge_recursive(
                $config->get('graphql.schemas', []),
                $schemas,
            ));
        }
    }
}
