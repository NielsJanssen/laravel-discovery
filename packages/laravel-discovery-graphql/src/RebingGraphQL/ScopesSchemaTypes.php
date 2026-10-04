<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ImplementingType;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\Type as GraphQLType;
use GraphQL\Type\Schema;
use Rebing\GraphQL\Exception\SchemaNotFound;
use Rebing\GraphQL\Support\Type as RebingType;

/**
 * Limits each schema Rebing builds to its own types and the global ones; use it in a subclass of Rebing's GraphQL.
 *
 * @phpstan-require-extends \Rebing\GraphQL\GraphQL
 */
trait ScopesSchemaTypes
{
    private ?string $scopedSchema = null;

    /** @var array<string, list<string>>|null the schemas whose own types key lists a type, keyed by type name */
    private ?array $schemaTypes = null;

    public function schema(?string $schemaName = null): Schema
    {
        $schemaName ??= $this->config->string('graphql.default_schema', 'default');
        $this->registerSchemaTypes();
        $previous = $this->scopedSchema;
        $this->scopedSchema = $schemaName;

        try {
            return parent::schema($schemaName);
        } finally {
            $this->scopedSchema = $previous;
        }
    }

    /**
     * @param  array<string, mixed>  $schemaConfig
     */
    public function buildSchemaFromConfig(array $schemaConfig): Schema
    {
        $schema = parent::buildSchemaFromConfig($schemaConfig);
        $name = $this->scopedSchema;

        if ($name === null) {
            return $schema;
        }

        $registry = $this->app->make(TypeRegistry::class);
        $config = clone $schema->getConfig();
        $loader = $config->getTypeLoader();
        $scoped = new Schema($config);

        $config->setTypes(function () use ($registry, $name): array {
            $types = [];

            foreach (array_keys($this->getTypes()) as $type) {
                $type = (string) $type;
                $named = $this->getType($type);

                if (! $this->allowsType($registry, $name, $type) && ! ($named instanceof ImplementingType && $this->implementsAllowed($registry, $name, $named))) {
                    continue;
                }

                if ($named instanceof NamedType) {
                    $types[] = $named;
                }
            }

            return $types;
        });

        $config->setTypeLoader(function (string $type) use ($registry, $loader, $name, $scoped): ?GraphQLType {
            if (! $this->allowsType($registry, $name, $type)) {
                return $scoped->getTypeMap()[$type] ?? null;
            }

            $loaded = $loader === null ? null : $loader($type);

            return $loaded instanceof GraphQLType ? $loaded : null;
        });

        return $scoped;
    }

    /**
     * Registers a type, and drops the kept instance of a name that now points at another class.
     *
     * @param  object|string  $class
     */
    public function addType($class, ?string $name = null): void
    {
        $name ??= $this->typeName($class);
        $previous = $name === null ? null : ($this->getTypes()[$name] ?? null);

        parent::addType($class, $name);

        if ($name !== null && $previous !== $class) {
            unset($this->typesInstances[$name]);
        }
    }

    /** The GraphQL name of a Rebing type class or instance, which Rebing's addType() reads when none is given. */
    private function typeName(object|string $class): ?string
    {
        $type = is_object($class) ? $class : $this->app->make($class);
        $name = $type instanceof RebingType ? ($type->getAttributes()['name'] ?? null) : null;

        return is_string($name) ? $name : null;
    }

    public function clearSchema(string $name): void
    {
        parent::clearSchema($name);
        $this->schemaTypes = null;
    }

    public function clearSchemas(): void
    {
        parent::clearSchemas();
        $this->schemaTypes = null;
    }

    /** Keeps the type instances when another schema is built, so schemas that share a type share its instance. */
    protected function clearTypeInstances(): void {}

    private function allowsType(TypeRegistry $registry, string $schema, string $type): bool
    {
        $schemas = $this->schemaTypes[$type] ?? null;

        if ($schemas === null) {
            return $registry->allows($schema, $type);
        }

        return in_array($schema, $schemas, true) || $registry->reaches($schema, $type);
    }

    /** Whether the type implements an interface the schema lists, which webonyx only finds through the types list. */
    private function implementsAllowed(TypeRegistry $registry, string $schema, ImplementingType $type): bool
    {
        foreach ($type->getInterfaces() as $interface) {
            if ($this->allowsType($registry, $schema, $interface->name())) {
                return true;
            }
        }

        return false;
    }

    /** Registers the types key of every schema at once, so a schema never depends on another having been built. */
    private function registerSchemaTypes(): void
    {
        if ($this->schemaTypes !== null) {
            return;
        }

        $this->schemaTypes = [];

        foreach (array_keys($this->config->array('graphql.schemas', [])) as $schema) {
            try {
                $types = static::getNormalizedSchemaConfiguration((string) $schema)['types'] ?? [];
            } catch (SchemaNotFound) {
                continue;
            }

            foreach (is_array($types) ? $types : [] as $key => $class) {
                if (! is_string($class) && ! is_object($class)) {
                    continue;
                }

                $this->addType($class, is_string($key) ? $key : null);
                $name = array_search($class, $this->getTypes(), true);

                if (is_string($name)) {
                    $this->schemaTypes[$name][] = (string) $schema;
                }
            }
        }
    }
}
