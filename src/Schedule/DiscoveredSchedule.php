<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use InvalidArgumentException;

final readonly class DiscoveredSchedule
{
    public function __construct(
        public string $className,
        public string $methodName,
        public string $name,
        public string|Frequency|null $schedule,
        public int $attributeIndex,
    ) {}

    public function toCron(string|Frequency $every): string
    {
        $value = $every instanceof Frequency ? $every->value : $every;

        return match (true) {
            $value === 'minute' => '* * * * *',
            (bool) preg_match('/^(\d+) minutes?$/', $value, $m) => "*/{$m[1]} * * * *",
            $value === 'hour' || $value === 'hourly' => '0 * * * *',
            (bool) preg_match('/^(\d+) hours?$/', $value, $m) => "0 */{$m[1]} * * *",
            $value === 'day' || $value === 'daily' => '0 0 * * *',
            $value === 'week' || $value === 'weekly' => '0 0 * * 0',
            $value === 'month' || $value === 'monthly' => '0 0 1 * *',
            $value === 'quarter' || $value === 'quarterly' => '0 0 1 */3 *',
            $value === 'year' || $value === 'yearly' => '0 0 1 1 *',
            default => throw new InvalidArgumentException("Unrecognised schedule frequency: \"{$value}\"."),
        };
    }
}
