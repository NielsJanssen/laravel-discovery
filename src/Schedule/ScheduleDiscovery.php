<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use ReflectionMethod;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

#[Singleton]
final class ScheduleDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
        private readonly Schedule $schedule,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->isInstantiable()) {
            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            $attrs = $method->getAttributes(Scheduled::class);

            if (empty($attrs)) {
                continue;
            }

            $multiple    = count($attrs) > 1;
            $defaultName = $class->getName() . '@' . $method->getName();

            foreach ($attrs as $index => $scheduled) {
                $this->discoveryItems->add($location, new DiscoveredSchedule(
                    className: $class->getName(),
                    methodName: $method->getName(),
                    name: $scheduled->name ?? ($multiple ? $defaultName . '#' . $index : $defaultName),
                    schedule: $scheduled->schedule instanceof \Closure ? null : $scheduled->schedule,
                    attributeIndex: $index,
                ));
            }
        }
    }

    public function apply(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        foreach ($this->discoveryItems as $item) {
            $event = $this->schedule->call(function () use ($item) {
                $this->app->call([$this->app->make($item->className), $item->methodName]);
            });

            $event->name($item->name);

            if ($item->schedule === null) {
                $scheduled = (new ReflectionMethod($item->className, $item->methodName))
                    ->getAttributes(Scheduled::class)[$item->attributeIndex]
                    ->newInstance();

                ($scheduled->schedule)($event);
            } else {
                $event->cron($item->toCron($item->schedule));
            }
        }
    }
}
