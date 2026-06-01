<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Illuminate\Console\Command as LaravelCommand;
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

        $classDecorators = $class->getAttributes(ScheduleDecorator::class);

        /** @var Scheduled|null $classAttr */
        $classAttr = $class->getAttribute(Scheduled::class);

        if ($classAttr) {
            if (!is_subclass_of($class->getName(), LaravelCommand::class)) {
                throw new \LogicException("Only commands extending " . LaravelCommand::class . " can be scheduled on the class level, but {$class->getName()} does not.");
            }

            $this->discoveryItems->add($location, DiscoveredSchedule::from(
                $classAttr->withDecorators($classDecorators),
                $class,
                null,
            ));

            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            $attrs = $method->getAttributes(Scheduled::class);

            if (empty($attrs)) {
                continue;
            }

            $decorators = [
                ...$classDecorators,
                ...$method->getAttributes(ScheduleDecorator::class),
            ];

            foreach ($attrs as $index => $scheduled) {
                $this->discoveryItems->add($location, DiscoveredSchedule::from(
                    $scheduled->withDecorators($decorators),
                    $class,
                    $method,
                    $index,
                ));
            }
        }
    }

    public function apply(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        /** @var DiscoveredSchedule $item */
        foreach ($this->discoveryItems as $item) {
            $event = $item->methodName
                ? $this->schedule->call(function () use ($item) {
                    $this->app->call([$this->app->make($item->className), $item->methodName]);
                })
                : $this->schedule->command($item->className);

            $event->name($item->name);

            $scheduled = $item->schedule;

            if (!isset($scheduled->schedule)) {
                $scheduledWithClosure = new ReflectionMethod($item->className, $item->methodName)
                    ->getAttributes(Scheduled::class)[$item->attributeIndex]
                    ->newInstance();

                ($scheduledWithClosure->schedule)($event);
            } elseif ($scheduled->schedule instanceof Cron) {
                $event->cron($scheduled->schedule->expression);
            } elseif ($scheduled->schedule instanceof Every) {
                $interval = $scheduled->schedule->asInterval();
                $event->cron($interval->toCronExpression());

                if ($interval->seconds) {
                    $event->repeatEvery($interval->seconds);
                }
            }

            if ($scheduled->between) {
                $event->between($scheduled->between->startTime, $scheduled->between->endTime);
            }

            if ($scheduled->unlessBetween) {
                $event->unlessBetween($scheduled->unlessBetween->startTime, $scheduled->unlessBetween->endTime);
            }

            if ($scheduled->withoutOverlapping) {
                $event->withoutOverlapping($scheduled->withoutOverlappingExpiry, $scheduled->releaseOnTerminationSignals);
            }

            if ($scheduled->onOneServer) {
                $event->onOneServer();
            }

            if ($scheduled->timezone !== null) {
                $event->timezone($scheduled->timezone);
            }
        }
    }
}
