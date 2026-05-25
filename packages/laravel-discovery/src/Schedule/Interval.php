<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

class Interval
{
    public function __construct(
        public ?int $years = null,
        public ?int $months = null,
        public ?int $weeks = null,
        public ?int $days = null,
        public ?int $hours = null,
        public ?int $minutes = null,
        public ?int $seconds = null,
    ) {
        if (
            !$this->years
            && !$this->months
            && !$this->weeks
            && !$this->days
            && !$this->hours
            && !$this->minutes
            && !$this->seconds
        ) {
            throw new \InvalidArgumentException('At least one interval value must be provided.');
        }
    }

    public function toCronExpression(): string
    {
        $hasLargerUnit = $this->hours !== null || $this->days !== null || $this->weeks !== null || $this->months !== null || $this->years !== null;

        $minute = match (true) {
            $this->minutes !== null && $hasLargerUnit => (string) $this->minutes,
            $this->minutes !== null => $this->minutes === 1 ? '*' : "*/$this->minutes",
            $hasLargerUnit => '0',
            default => '*',
        };

        $hour = match (true) {
            $this->hours !== null => $this->hours === 1 ? '*' : "*/$this->hours",
            $this->days !== null, $this->weeks !== null, $this->months !== null, $this->years !== null => '0',
            default => '*',
        };

        $day = match (true) {
            $this->days !== null => $this->days === 1 ? '*' : "*/$this->days",
            $this->weeks !== null => '*/' . ($this->weeks * 7),
            $this->months !== null, $this->years !== null => '1',
            default => '*',
        };

        $month = match (true) {
            $this->months !== null => $this->months === 1 ? '*' : "*/$this->months",
            $this->years !== null => '1',
            default => '*',
        };

        return "$minute $hour $day $month *";
    }
}
