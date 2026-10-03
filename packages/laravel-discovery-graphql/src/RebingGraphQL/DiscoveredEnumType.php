<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Rebing\GraphQL\Support\EnumType as RebingEnumType;
use RuntimeException;
use UnitEnum;

/** A Rebing enum type built from a DiscoveredType; each value's internal value is its case. */
final class DiscoveredEnumType extends RebingEnumType
{
    public function __construct(
        private readonly DiscoveredType $discoveredType,
    ) {}

    public function attributes(): array
    {
        $attributes = [
            'name' => $this->discoveredType->name,
            'values' => $this->values(),
        ];

        if ($this->discoveredType->description !== null) {
            $attributes['description'] = $this->discoveredType->description;
        }

        return $attributes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function values(): array
    {
        $name = $this->discoveredType->name;
        $class = $this->discoveredType->class;

        if (! is_a($class, UnitEnum::class, true)) {
            throw new RuntimeException("Cannot build GraphQL enum [$name]: $class is not an enum.");
        }

        $cases = [];

        foreach ($class::cases() as $case) {
            $cases[$case->name] = $case;
        }

        $values = [];

        foreach ($this->discoveredType->values as $value) {
            $entry = [
                'value' => $cases[$value->name] ?? throw new RuntimeException(
                    "Cannot build GraphQL enum [$name]: $class has no case {$value->name}. Clear the discovery cache.",
                ),
            ];

            if ($value->description !== null) {
                $entry['description'] = $value->description;
            }

            if ($value->deprecationReason !== null) {
                $entry['deprecationReason'] = $value->deprecationReason;
            }

            $values[$value->name] = $entry;
        }

        return $values;
    }
}
