<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use LogicException;
use Rebing\GraphQL\GraphQL;

/** Registers the types of every discovered TypeProvider with Rebing when its GraphQL is resolved. */
final readonly class ProvidedTypes
{
    public function __construct(
        private Application $app,
        private Repository $config,
        private TypeRegistry $registry,
        private FactoryFields $fields,
        private Naming\Naming $names,
    ) {}

    public function register(GraphQL $graphQL): void
    {
        foreach ($this->registry->providers() as $class) {
            $provider = $this->app->make($class);

            if (! $provider instanceof TypeProvider) {
                throw new LogicException(sprintf('The type provider %s resolves to %s, which is no %s.', $class, get_debug_type($provider), TypeProvider::class));
            }

            foreach ($provider->types() as $definition) {
                $this->add($graphQL, $class, $definition);
            }
        }
    }

    /**
     * @param  class-string  $provider
     */
    private function add(GraphQL $graphQL, string $provider, mixed $definition): void
    {
        if (! $definition instanceof TypeDefinition) {
            throw new LogicException(sprintf('The type provider %s yielded %s, which is no %s.', $provider, get_debug_type($definition), TypeDefinition::class));
        }

        if (array_key_exists($definition->name, $graphQL->getTypes()) || array_key_exists($definition->name, $this->config->array('graphql.types', []))) {
            throw new LogicException(sprintf('The type provider %s yields a type named [%s], which is already registered. Rename the provided type, or drop one of the two.', $provider, $definition->name));
        }

        if ($definition->kind === Position::Input && $definition->class !== null) {
            throw new LogicException(sprintf('The type provider %s yields the input type [%s] with class: %s, which is not supported yet. Remove class:, and take the value as an array with #[Arg(type: \'%s\')].', $provider, $definition->name, $definition->class, $definition->name));
        }

        $fields = new ProvidedFields($definition, $provider, $this->fields, $this->names);

        $graphQL->addType($definition->kind === Position::Input ? new ProvidedInputType($fields) : new ProvidedObjectType($fields), $definition->name);
    }
}
