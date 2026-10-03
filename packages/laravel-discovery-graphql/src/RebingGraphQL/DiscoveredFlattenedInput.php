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

    /** Translates a property path, `title` or `title.min`, to the arg path a validator reports against. */
    public function toArgPath(string $propertyPath): string
    {
        $segments = explode('.', $propertyPath, 2);

        foreach ($this->type->fields as $field) {
            if ($field->phpName === $segments[0]) {
                return isset($segments[1]) ? "{$field->name}.{$segments[1]}" : $field->name;
            }
        }

        return $propertyPath;
    }
}
