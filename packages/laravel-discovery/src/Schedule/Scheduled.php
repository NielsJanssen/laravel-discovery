<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Attribute;
use Illuminate\Console\Scheduling\Event;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Scheduled
{
    /**
     * @param Cron|Every|\Closure(Event): void $schedule
     */
    public function __construct(
        private(set) Cron|Every|\Closure $schedule,
        public readonly ?BetweenTime $between = null,
        public readonly ?BetweenTime $unlessBetween = null,
        public readonly ?string $name = null,
        public bool $withoutOverlapping = false,
        public bool $onOneServer = false,
        public ?string $timezone = null,
        public array $parameters = [],
    ) {}

    public int $withoutOverlappingExpiry = 1440;
    public bool $releaseOnTerminationSignals = true;

    public function clearClosure(): void
    {
        if ($this->schedule instanceof \Closure) {
            unset($this->schedule);
        }
    }

    public function withDecorators(array $decorators): static
    {
        $self = clone $this;

        foreach ($decorators as $decorator) {
            $decorator->decorate($self);
        }

        return $self;
    }
}
