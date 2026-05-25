<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\Middleware\Benchmark;
use NielsJanssen\Laravel\Discovery\Event\EventHandler;
use Tempest\Discovery\ClearDiscoveryCache;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\GenerateDiscoveryCache;

use function Laravel\Prompts\warning;

final readonly class DiscoveryCacheCommand
{
    public function __construct(
        private Application $app,
        private DiscoveryCache $cache,
    ) {}

    #[ConsoleCommand('discovery:cache', middleware: [Benchmark::class])]
    public function generate(Repository $config): void
    {
        $this->app->call(GenerateDiscoveryCache::class, [
            'discoveryClasses' => $config->get('discovery.discovery_classes', []),
        ]);
    }

    #[ConsoleCommand('discovery:clear', middleware: [Benchmark::class])]
    public function clear(): void
    {
        $this->app->call(ClearDiscoveryCache::class, [
            'cache' => $this->cache,
        ]);
    }

    #[EventHandler(deferred: true)]
    public function after(CommandFinished $event): void
    {
        if (in_array($event->command, ['discovery:cache', 'optimize']) && $this->cache->strategy !== DiscoveryCacheStrategy::FULL) {
            warning('Discovery caching is disabled in the current environment, cache was not generated.');
        }
    }
}
