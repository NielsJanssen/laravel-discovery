<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use ReflectionAttribute;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/** Points at a field decorator that cannot be serialized, so it is read again by reflection. */
final readonly class FieldDecoratorReference
{
    public function __construct(
        public FieldSource $source,
        public string $member,
        public int $index,
    ) {}

    public static function storable(FieldDecorator $decorator, FieldSource $source, string $member, int $index): FieldDecorator|self
    {
        try {
            serialize($decorator);

            return $decorator;
        } catch (Throwable) {
            return new self($source, $member, $index);
        }
    }

    /**
     * @param  class-string  $class
     */
    public function resolve(string $class): FieldDecorator
    {
        $reflection = $this->source === FieldSource::Method
            ? new ReflectionMethod($class, $this->member)
            : new ReflectionProperty($class, $this->member);

        $decorator = ($reflection->getAttributes(FieldDecorator::class, ReflectionAttribute::IS_INSTANCEOF)[$this->index] ?? null)?->newInstance();

        return $decorator instanceof FieldDecorator ? $decorator : throw new RuntimeException(sprintf(
            'Field decorator #%d on %s::%s is gone. Run discovery:clear after changing a #[Type] class.',
            $this->index,
            $class,
            $this->member,
        ));
    }
}
