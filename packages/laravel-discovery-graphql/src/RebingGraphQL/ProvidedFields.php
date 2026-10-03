<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;

/** What a Rebing type adapter needs of a TypeDefinition: its attributes and its field definitions. */
final class ProvidedFields
{
    /** @var list<Field>|null what the definition's fields closure yielded, read once */
    private ?array $yielded = null;

    /** The #[TypeExtension] fields added after the provided ones. */
    private ?DiscoveredType $extension = null;

    /**
     * @param  class-string  $provider
     */
    public function __construct(
        private readonly TypeDefinition $definition,
        private readonly string $provider,
        private readonly FactoryFields $fields,
        private readonly ObjectFields $objects,
        private readonly Naming $names,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = ['name' => $this->definition->name];

        if ($this->definition->description !== null) {
            $attributes['description'] = $this->definition->description;
        }

        return $attributes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function build(): array
    {
        $definitions = $this->fields->build($this->owner(), $this->yielded(), [], $this->definition->kind);

        if ($this->extension === null) {
            return $definitions;
        }

        return $definitions + $this->objects->build($this->extension, $this->definition->class, array_keys($definitions));
    }

    /**
     * The names of the provided fields, each pointing at the provider as its source.
     *
     * @return array<string, string>
     */
    public function sources(): array
    {
        $sources = [];

        foreach ($this->yielded() as $field) {
            if ($field->name !== null) {
                $sources[$field->name] = "the type provider {$this->provider}";
            }
        }

        return $sources;
    }

    public function extend(DiscoveredType $extension): void
    {
        $this->extension = $extension;
    }

    /**
     * @return list<Field>
     */
    private function yielded(): array
    {
        if ($this->yielded === null) {
            $name = $this->definition->name;
            $context = new TypeContext($name, $this->definition->class, $this->definition->kind, $this->names->fields(null, $this->owner()->namingLabel), []);
            $this->yielded = iterator_to_array(($this->definition->fields)($context), false);
        }

        return $this->yielded;
    }

    private function owner(): FactoryOwner
    {
        $name = $this->definition->name;

        return new FactoryOwner($name, "type provider {$this->provider}", "type [$name]", "the type provider {$this->provider}");
    }
}
