<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\Extensions;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\SchemaScopes;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\SchemaValidator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\Support\Facades\GraphQL as GraphQLFacade;
use Throwable;

/** Registers the types of every discovered TypeProvider with Rebing when its GraphQL is resolved. */
final readonly class ProvidedTypes
{
    public function __construct(
        private Application $app,
        private Repository $config,
        private TypeRegistry $registry,
        private FactoryFields $fields,
        private ObjectFields $objects,
        private Naming $names,
        private SchemaValidator $validator,
        private SchemaScopes $scopes,
    ) {}

    public function register(GraphQL $graphQL): void
    {
        try {
            $this->registerAll($graphQL);
        } catch (Throwable $e) {
            $this->app->forgetInstance(GraphQL::class);
            GraphQLFacade::clearResolvedInstance(GraphQL::class);

            throw $e;
        }
    }

    private function registerAll(GraphQL $graphQL): void
    {
        $pending = $this->registry->deferredExtensions();

        foreach ($this->registry->providers() as $class) {
            $provider = $this->app->make($class);

            if (! $provider instanceof TypeProvider) {
                throw new LogicException(sprintf('The type provider %s resolves to %s, which is no %s.', $class, get_debug_type($provider), TypeProvider::class));
            }

            foreach ($provider->types() as $definition) {
                $this->add($graphQL, $class, $definition, $pending);
            }
        }

        if ($pending !== []) {
            throw Extensions::unknown($pending[array_key_first($pending)]);
        }

        $this->validator->assertRegistered($this->registry->deferredReferences());
    }

    /**
     * @param  class-string  $provider
     * @param  array<int, DiscoveredExtension>  $pending  the extensions not yet matched, of which this definition takes its own
     */
    private function add(GraphQL $graphQL, string $provider, mixed $definition, array &$pending): void
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

        if ($definition->class !== null) {
            $this->registry->register($definition->class, $definition->name, TypeKind::Object);
        }

        $fields = new ProvidedFields($definition, $provider, $this->fields, $this->objects, $this->names);
        $extensions = $this->extensionsOf($definition, $pending);

        if ($extensions !== []) {
            $shell = new DiscoveredType($definition->name, $definition->class ?? $extensions[0]->class, description: $definition->description);

            $fields->extend(Extensions::merge($shell, $extensions, $definition->class, $fields->sources()));
        }

        $this->scopes->provide($provider, $definition);
        $graphQL->addType($definition->kind === Position::Input ? new ProvidedInputType($fields) : new ProvidedObjectType($fields), $definition->name);
    }

    /**
     * Takes the pending extensions that name the definition, by GraphQL name or by its class.
     *
     * @param  array<int, DiscoveredExtension>  $pending
     * @return list<DiscoveredExtension>
     */
    private function extensionsOf(TypeDefinition $definition, array &$pending): array
    {
        $matched = [];

        foreach ($pending as $key => $extension) {
            if ($extension->targetIsClass ? $extension->target !== $definition->class : $extension->target !== $definition->name) {
                continue;
            }

            if ($definition->kind === Position::Input) {
                Extensions::assertExtendable($extension->label(), "[{$definition->name}]", TypeKind::Input);
            }

            $matched[] = $extension;
            unset($pending[$key]);
        }

        return $matched;
    }
}
