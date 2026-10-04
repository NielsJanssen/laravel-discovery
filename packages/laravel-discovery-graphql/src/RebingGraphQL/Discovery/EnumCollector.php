<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Deprecated;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredEnumValue;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Enum;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\EnumValue;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use ReflectionEnum;

/** Reads a PHP enum and its cases into a DiscoveredType. */
final readonly class EnumCollector
{
    /**
     * @param  class-string  $class
     */
    public function collect(string $class, bool $implicit = false): DiscoveredType
    {
        if (! enum_exists($class)) {
            throw new LogicException(sprintf(
                '#[Enum] on %s, which is not a PHP enum: only an enum becomes a GraphQL enum. Declare %s as an enum, or use #[Type] for an object type.',
                $class,
                class_basename($class),
            ));
        }

        $reflection = new ReflectionEnum($class);
        $enum = ($reflection->getAttributes(Enum::class)[0] ?? null)?->newInstance();
        $values = [];

        foreach ($reflection->getCases() as $case) {
            $value = ($case->getAttributes(EnumValue::class)[0] ?? null)?->newInstance();
            $deprecated = ($case->getAttributes(Deprecated::class)[0] ?? null)?->newInstance();

            $values[] = new DiscoveredEnumValue(
                name: $case->getName(),
                description: $value?->description,
                deprecationReason: DeprecationReason::from($deprecated),
            );
        }

        $name = $enum?->name;

        return new DiscoveredType(
            name: $name ?? $reflection->getShortName(),
            class: $class,
            kind: TypeKind::Enum,
            description: $enum?->description,
            values: $values,
            implicit: $implicit,
            schemas: (array) $enum?->schema,
        );
    }
}
