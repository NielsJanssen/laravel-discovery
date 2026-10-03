<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use PropertyHookType;
use ReflectionProperty;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;

/** A public, non-static member of a type or input class that may become a field. */
final readonly class FieldMember
{
    public function __construct(
        /** How errors name the member, e.g. "Property Acme\Book::$title". */
        public string $label,
        public ?Field $field,
        public PropertyReflector|MethodReflector $reflector,
    ) {}

    /** Whether the property is virtual and lacks the hook that reads or fills it. */
    public function lacksHook(PropertyHookType $type): bool
    {
        $reflection = $this->reflector->getReflection();

        return $reflection instanceof ReflectionProperty && $reflection->isVirtual() && ! $reflection->hasHook($type);
    }

    /** Skips a member without #[Field] and rejects one with it, `$problem` completing "has #[Field] but ...". */
    public function skipOrReject(string $problem): null
    {
        return $this->field === null ? null : throw new LogicException("{$this->label} has #[Field] but $problem");
    }
}
