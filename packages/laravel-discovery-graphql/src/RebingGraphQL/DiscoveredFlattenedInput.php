<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** An #[AsArgs] parameter: the fields of its #[Input] class, each a top-level arg. */
final readonly class DiscoveredFlattenedInput
{
    /**
     * @param  DiscoveredType  $type  the #[Input] class's fields
     */
    public function __construct(
        public string $paramName,
        public DiscoveredType $type,
    ) {}

    /**
     * The values of this input's fields among the request args, keyed by property name.
     *
     * @param  array<array-key, mixed>  $args
     * @return array<string, mixed>
     */
    public function toProperties(array $args): array
    {
        return $this->type->toProperties($args);
    }

    /**
     * Whether a property's rules stay out because its Omitted field was left out or sent as a rejected null.
     *
     * @param  array<array-key, mixed>  $args
     */
    public function skipsRulesOf(string $propertyPath, array $args): bool
    {
        $field = $this->fieldOf(explode('.', $propertyPath, 2)[0]);

        return $field !== null && $field->omittedRules($args) !== null;
    }

    /** Translates a property path, `title` or `title.min`, to the arg path a validator reports against. */
    public function toArgPath(string $propertyPath): string
    {
        $segments = explode('.', $propertyPath, 2);
        $field = $this->fieldOf($segments[0]);

        if ($field === null) {
            return $propertyPath;
        }

        return isset($segments[1]) ? "{$field->name}.{$segments[1]}" : $field->name;
    }

    private function fieldOf(string $property): ?DiscoveredTypeField
    {
        foreach ($this->type->fields as $field) {
            if ($field->phpName === $property) {
                return $field;
            }
        }

        return null;
    }
}
