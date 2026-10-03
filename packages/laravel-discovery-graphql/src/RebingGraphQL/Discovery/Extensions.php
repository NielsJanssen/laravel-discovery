<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use Tempest\Discovery\DiscoveryItems;

/** The #[TypeExtension] contributors of each discovered type, matched once all items are known. */
final class Extensions
{
    /** @var array<class-string, list<DiscoveredExtension>> keyed by the class of the extended type */
    private array $matched = [];

    /** @var list<DiscoveredExtension> the extensions left for the type providers */
    private array $deferred = [];

    private function __construct() {}

    public static function from(DiscoveryItems $items, Replacements $replacements): self
    {
        $extensions = new self();
        $byClass = [];
        $byName = [];
        $handWritten = [];
        $contributors = [];
        $providers = false;

        foreach ($items as $item) {
            $type = $item instanceof DiscoveredType ? $replacements->effective($item) : null;

            if ($type !== null) {
                $byClass[$type->kind->value][$type->class] = $type;
                $byName[$type->name] = $type;
            } elseif ($item instanceof DiscoveredField && $item->fieldType === 'types' && ($name = $item->getName()) !== null) {
                $handWritten[$name] = $item->class;
            } elseif ($item instanceof DiscoveredExtension) {
                $contributors[] = $item;
            } elseif ($item instanceof DiscoveredTypeProvider) {
                $providers = true;
            }
        }

        foreach (self::sorted($contributors) as $extension) {
            $type = $extension->targetIsClass && class_exists($extension->target)
                ? $byClass[TypeKind::Object->value][$replacements->replacementOf(TypeKind::Object, $extension->target)] ?? null
                : $byName[$extension->target] ?? null;

            if ($type !== null) {
                self::assertExtendable($extension->label(), "[{$type->name}]", $type->kind);
                $extensions->matched[$type->class][] = $extension;
            } elseif (isset($handWritten[$extension->target])) {
                throw new LogicException(sprintf(
                    '%s names the hand-written Rebing type %s, which cannot be extended. Add the field to that class.',
                    $extension->label(),
                    $handWritten[$extension->target],
                ));
            } elseif ($providers) {
                $extensions->deferred[] = $extension;
            } else {
                throw self::unknown($extension);
            }
        }

        return $extensions;
    }

    /**
     * @param  list<DiscoveredType>  $types
     * @return list<DiscoveredType>
     */
    public function extend(array $types): array
    {
        return array_map(fn(DiscoveredType $type): DiscoveredType => $type->kind === TypeKind::Object && isset($this->matched[$type->class])
            ? self::merge($type, $this->matched[$type->class], $type->class, array_combine(
                array_column($type->fields, 'name'),
                array_map(static fn(DiscoveredTypeField $field): string => $field->member(), $type->fields),
            ))
            : $type, $types);
    }

    /**
     * @return list<DiscoveredExtension>
     */
    public function deferred(): array
    {
        return $this->deferred;
    }

    /**
     * The type with the contributed fields after its own, rejecting a name that two sources give.
     *
     * @param  list<DiscoveredExtension>  $extensions
     * @param  class-string|null  $class  the class of the type, which a provided type may lack
     * @param  array<string, string>  $taken  the sources of the names the type already has, keyed by field name
     */
    public static function merge(DiscoveredType $type, array $extensions, ?string $class, array $taken): DiscoveredType
    {
        $fields = [];
        $factories = [];

        foreach ($extensions as $extension) {
            foreach ($extension->fieldsFor($class) as $field) {
                $previous = $taken[$field->name] ?? null;

                if ($previous !== null) {
                    throw new LogicException(sprintf(
                        'Type [%s] gets the field "%s" from both %s and %s. Rename one with #[Field(name: ...)].',
                        $type->name,
                        $field->name,
                        $previous,
                        $field->member(),
                    ));
                }

                $taken[$field->name] = $field->member();
                $fields[] = $field;
            }

            if (is_a($extension->class, TypeFactory::class, true)) {
                $factories[] = $extension->class;
            }
        }

        return clone($type, ['fields' => [...$type->fields, ...$fields], 'extensionFactories' => $factories]);
    }

    /** Rejects an extension of a type that has no output fields to add to, the type named as "[Name]" or by its class. */
    public static function assertExtendable(string $label, string $type, TypeKind $kind): void
    {
        if ($kind === TypeKind::Input) {
            throw new LogicException(sprintf(
                '%s targets the input type %s, and inputs cannot be extended. Extend an #[Input] with a subclass marked #[Input(replace: true)], or add the field where the input is defined.',
                $label,
                $type,
            ));
        }

        if ($kind === TypeKind::Enum) {
            throw new LogicException(sprintf('%s targets the enum %s, which has no fields to extend.', $label, $type));
        }
    }

    public static function unknown(DiscoveredExtension $extension): LogicException
    {
        return new LogicException(sprintf(
            '%s names no discovered #[Type] and no provided type. Add #[Type] to the class, or name an existing type by its class or GraphQL name.',
            $extension->label(),
        ));
    }

    /**
     * @param  list<DiscoveredExtension>  $extensions
     * @return list<DiscoveredExtension>
     */
    private static function sorted(array $extensions): array
    {
        usort($extensions, static fn(DiscoveredExtension $a, DiscoveredExtension $b): int => strcmp($a->class, $b->class));

        return $extensions;
    }
}
