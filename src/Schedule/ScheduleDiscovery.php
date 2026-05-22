<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
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
            if (empty($method->getAttributes(Scheduled::class))) {
                continue;
            }

            $this->discoveryItems->add($location, new DiscoveredSchedule(
                className: $class->getName(),
                methodName: $method->getName(),
            ));
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as $item) {
            $reflection = new ReflectionMethod($item->className, $item->methodName);
            $attrs    = $reflection->getAttributes(Scheduled::class);
            $multiple = count($attrs) > 1;

            foreach ($attrs as $index => $attr) {
                $scheduled = $attr->newInstance();

                $event = $this->schedule->call(function () use ($item) {
                    $this->app->call([$this->app->make($item->className), $item->methodName]);
                });

                $defaultName = $item->className . '@' . $item->methodName;
                $event->name($scheduled->name ?? ($multiple ? $defaultName . '#' . $index : $defaultName));

                if ($scheduled->schedule instanceof \Closure) {
                    ($scheduled->schedule)($event);
                } else {
                    $event->cron($this->toCron($scheduled->schedule));
                }
            }
        }
    }

    private function toCron(string|Frequency $every): string
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
