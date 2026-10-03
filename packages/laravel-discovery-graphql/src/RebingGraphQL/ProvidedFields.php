<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;

/** What a Rebing type adapter needs of a TypeDefinition: its attributes and its field definitions. */
final readonly class ProvidedFields
{
    /**
     * @param  class-string  $provider
     */
    public function __construct(
        private TypeDefinition $definition,
        private string $provider,
        private FactoryFields $fields,
        private Naming $names,
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
        $name = $this->definition->name;
        $owner = new FactoryOwner($name, "type provider {$this->provider}", "type [$name]", "the type provider {$this->provider}");
        $context = new TypeContext($name, $this->definition->class, $this->definition->kind, $this->names->fields(null, $owner->namingLabel), []);

        return $this->fields->build($owner, ($this->definition->fields)($context), [], $this->definition->kind);
    }
}
