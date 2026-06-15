<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Illuminate\Console\Command as LaravelCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Application;
use ReflectionClass;
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

    /**
     * @param ClassReflector<object> $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (! $class->isInstantiable()) {
            return;
        }

        $classDecorators = $class->getAttributes(ScheduleDecorator::class);

        /** @var list<Scheduled> $classAttrs */
        $classAttrs = $class->getAttributes(Scheduled::class);

        foreach ($classAttrs as $index => $classAttr) {
            $target = match (true) {
                is_subclass_of($class->getName(), LaravelCommand::class) => ScheduleTarget::Command,
                is_subclass_of($class->getName(), ShouldQueue::class) => ScheduleTarget::Job,
                default => throw new \LogicException(
                    "Scheduled class {$class->getName()} must either "
                    . 'be a command extending ' . LaravelCommand::class
                    . ' or a job implementing ' . ShouldQueue::class,
                ),
            };

            $this->discoveryItems->add($location, DiscoveredSchedule::from(
                $classAttr->withDecorators($classDecorators),
                $class,
                null,
                index: $index,
                target: $target,
            ));

        }

        if (!empty($classAttrs)) {
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
            $event = match ($item->target) {
                ScheduleTarget::Command => $this->schedule->command($item->className, $item->schedule->parameters),
                ScheduleTarget::Job => $this->schedule->job($item->className),
                ScheduleTarget::Method => $this->schedule->call(function () use ($item) {
                    $method = $item->methodName
                        ?? throw new \LogicException('A method schedule target requires a method name.');

                    $this->app->call($item->className . '@' . $method, $item->schedule->parameters);
                }),
            };

            $event->name($item->name);

            $scheduled = $item->schedule;

            if (!isset($scheduled->schedule)) {
                $reflector = $item->methodName
                    ? new ReflectionMethod($item->className, $item->methodName)
                    : new ReflectionClass($item->className);

                $closure = $reflector
                    ->getAttributes(Scheduled::class)[$item->attributeIndex]
                    ->newInstance()
                    ->schedule;

                if ($closure instanceof \Closure) {
                    $closure($event);
                }
            } elseif ($scheduled->schedule instanceof Cron) {
                $event->cron($scheduled->schedule->expression);
            } elseif ($scheduled->schedule instanceof Every) {
                $interval = $scheduled->schedule->asInterval();
                $event->cron($interval->toCronExpression());

                if ($interval->seconds !== null) {
                    match ($interval->seconds) {
                        1 => $event->everySecond(),
                        2 => $event->everyTwoSeconds(),
                        5 => $event->everyFiveSeconds(),
                        10 => $event->everyTenSeconds(),
                        15 => $event->everyFifteenSeconds(),
                        20 => $event->everyTwentySeconds(),
                        30 => $event->everyThirtySeconds(),
                        default => throw new \InvalidArgumentException(
                            "Unsupported sub-minute interval: {$interval->seconds} seconds.",
                        ),
                    };
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
