<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

final class DiscoveredSchedule
{
    public function __construct(
        public readonly string $className,
        public readonly ?string $methodName,
        public readonly string $name,
        public readonly ?Scheduled $schedule,
        public readonly int $attributeIndex,
    ) {}

    public static function from(Scheduled $scheduled, ClassReflector $class, ?MethodReflector $method, int $index = 0): self
    {
        $scheduled->clearClosure();

        return new self(
            className: $class->getName(),
            methodName: $method?->getName(),
            name: $scheduled->name ?? ($class->getName() . ($method ? '@' . $method->getName() . '#' . $index : '')),
            schedule: $scheduled,
            attributeIndex: $index,
        );
    }
}
