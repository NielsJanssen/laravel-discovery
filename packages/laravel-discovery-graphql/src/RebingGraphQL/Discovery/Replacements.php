<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Reflection\ClassReflector;

/** The discovered types a subclass with replace: true takes over, resolved once all items are known. */
final class Replacements
{
    /** @var array<string, DiscoveredType> keyed by kind, then class */
    private array $types = [];

    /** @var array<string, DiscoveredType> the nearest discovered ancestor of each replacer, keyed by kind, then class */
    private array $parents = [];

    /** @var array<string, array<class-string, class-string>> the direct replacer of a type, keyed by kind, then class */
    private array $replacers = [];

    /** @var array<int, DiscoveredType> */
    private array $effective = [];

    private function __construct() {}

    public static function from(DiscoveryItems $items): self
    {
        $replacements = new self();

        foreach ($items as $item) {
            if ($item instanceof DiscoveredType && $item->kind !== TypeKind::Enum) {
                $replacements->types[self::key($item->kind, $item->class)] = $item;
            }
        }

        foreach ($replacements->types as $key => $item) {
            if ($item->replace) {
                $replacements->link($key, $item);
            }
        }

        $replacements->assertNotFlattened($items);

        return $replacements;
    }

    /**
     * Rejects a replace: true that can never name a parent, while the class is read.
     *
     * @param  ClassReflector<object>  $class
     */
    public static function assertDeclarable(ClassReflector $class, TypeKind $kind, bool $replace, bool $named): void
    {
        $attribute = $kind->attribute();

        if (! $replace) {
            return;
        }

        if ($named) {
            throw new LogicException(sprintf(
                '#[%s(replace: true)] on %s cannot set name:, because a replacement takes the GraphQL name of the type it replaces. Remove name:, or remove replace:.',
                $attribute,
                $class->getName(),
            ));
        }

        if ($class->getReflection()->getParentClass() === false) {
            throw new LogicException(sprintf(
                '#[%s(replace: true)] on %s, which has no parent class. A replacement extends the type it takes over: extend it, or remove replace:.',
                $attribute,
                $class->getName(),
            ));
        }
    }

    /** The type to register for an item: null for a replaced type, the renamed replacer, or the item itself. */
    public function effective(DiscoveredType $item): ?DiscoveredType
    {
        if (isset($this->replacers[$item->kind->value][$item->class])) {
            return null;
        }

        if (! $item->replace) {
            return $item;
        }

        return $this->resolved($item);
    }

    /**
     * Every replaced class with the class that finally replaces it.
     *
     * @return iterable<array{0: class-string, 1: class-string, 2: TypeKind}>
     */
    public function pairs(): iterable
    {
        foreach ($this->replacers as $kind => $replaced) {
            $kind = TypeKind::from($kind);

            foreach (array_keys($replaced) as $class) {
                yield [$class, $this->replacementOf($kind, $class), $kind];
            }
        }
    }

    /**
     * The class that finally replaces a class, or the class itself.
     *
     * @param  class-string  $class
     * @return class-string
     */
    public function replacementOf(TypeKind $kind, string $class): string
    {
        while (isset($this->replacers[$kind->value][$class])) {
            $class = $this->replacers[$kind->value][$class];
        }

        return $class;
    }

    private function resolved(DiscoveredType $item): DiscoveredType
    {
        $id = spl_object_id($item);

        if (isset($this->effective[$id])) {
            return $this->effective[$id];
        }

        $parent = $this->parents[self::key($item->kind, $item->class)];
        $inherited = $parent->replace ? $this->resolved($parent) : $parent;

        return $this->effective[$id] = clone($item, [
            'name' => $inherited->name,
            'description' => $item->description ?? $inherited->description,
            'schemas' => $item->schemas !== [] ? $item->schemas : $inherited->schemas,
        ]);
    }

    private function link(string $key, DiscoveredType $item): void
    {
        $attribute = $item->kind->attribute();

        for ($parent = get_parent_class($item->class); $parent !== false; $parent = get_parent_class($parent)) {
            $candidate = $this->types[self::key($item->kind, $parent)] ?? null;

            if ($candidate === null) {
                continue;
            }

            $taken = $this->replacers[$item->kind->value][$parent] ?? null;

            if ($taken !== null) {
                throw new LogicException(sprintf(
                    '%s and %s both replace %s with #[%s(replace: true)]. A type has one replacement: extend one from the other, or remove replace: from one.',
                    $taken,
                    $item->class,
                    $parent,
                    $attribute,
                ));
            }

            $this->parents[$key] = $candidate;
            $this->replacers[$item->kind->value][$parent] = $item->class;

            return;
        }

        throw new LogicException(sprintf(
            '#[%s(replace: true)] on %s, but no parent class of it is a discovered #[%s]. Add #[%s] to the parent, or remove replace:. A type provided with TypeDefinition(class:) cannot be replaced.',
            $attribute,
            $item->class,
            $attribute,
            $attribute,
        ));
    }

    private function assertNotFlattened(DiscoveryItems $items): void
    {
        foreach ($items as $item) {
            if (! $item instanceof DiscoveredAction) {
                continue;
            }

            foreach ($item->parameters->flattenedInputs as $flattened) {
                $replacer = $this->replacers[TypeKind::Input->value][$flattened->type->class] ?? null;

                if ($replacer !== null) {
                    throw new LogicException(sprintf(
                        '#[AsArgs] on the parameter $%s in %s::%s flattens %s, which %s replaces: flattened args are fixed at discovery. Take it as an #[Input] arg, or type the parameter as %s.',
                        $flattened->paramName,
                        $item->class,
                        $item->method,
                        $flattened->type->class,
                        $this->replacementOf(TypeKind::Input, $flattened->type->class),
                        $this->replacementOf(TypeKind::Input, $flattened->type->class),
                    ));
                }
            }
        }
    }

    private static function key(TypeKind $kind, string $class): string
    {
        return $kind->value . "\0" . $class;
    }
}
