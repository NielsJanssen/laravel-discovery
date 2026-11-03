<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Reflection;

use ReflectionAttribute;
use Tempest\Reflection\ClassReflector;

/** Reads class-level attributes from a class and the traits it uses directly. */
final class ClassAttributes
{
    /**
     * The class's own attributes, then those of each trait it uses directly, in `use` order.
     *
     * @template TAttribute of object
     * @param  ClassReflector<object>  $class
     * @param  class-string<TAttribute>  $attribute
     * @return list<TAttribute>
     */
    public static function of(ClassReflector $class, string $attribute): array
    {
        return array_merge(...self::groups($class, $attribute));
    }

    /**
     * The same attributes for decorators where a later one wins: traits in reverse `use` order, then the class's own.
     *
     * @template TAttribute of object
     * @param  ClassReflector<object>  $class
     * @param  class-string<TAttribute>  $attribute
     * @return list<TAttribute>
     */
    public static function inApplyOrder(ClassReflector $class, string $attribute): array
    {
        return array_merge(...array_reverse(self::groups($class, $attribute)));
    }

    /**
     * @template TAttribute of object
     * @param  ClassReflector<object>  $class
     * @param  class-string<TAttribute>  $attribute
     * @return list<list<TAttribute>>
     */
    private static function groups(ClassReflector $class, string $attribute): array
    {
        $groups = [array_values($class->getAttributes($attribute))];

        foreach ($class->getReflection()->getTraits() as $trait) {
            $groups[] = array_map(
                static fn(ReflectionAttribute $found): object => $found->newInstance(),
                $trait->getAttributes($attribute, ReflectionAttribute::IS_INSTANCEOF),
            );
        }

        return $groups;
    }
}
