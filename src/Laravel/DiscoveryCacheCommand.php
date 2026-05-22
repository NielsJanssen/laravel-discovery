<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Laravel;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\Command\ConsoleCommand;
use NielsJanssen\Laravel\Discovery\Command\Middleware\Benchmark;
use NielsJanssen\Laravel\Discovery\Event\EventHandler;
use Tempest\Discovery\ClearDiscoveryCache;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\GenerateDiscoveryCache;

use function Laravel\Prompts\warning;

final readonly class DiscoveryCacheCommand
{
    public function __construct(
        private DiscoveryCache $cache,
    ) {}

    #[ConsoleCommand('discovery:cache', middleware: [Benchmark::class])]
    public function generate(Application $app, GenerateDiscoveryCache $generate, DiscoveryConfig $config): void
    {
        $generate($app, $config, $this->cache);
    }

    #[ConsoleCommand('discovery:clear', middleware: [Benchmark::class])]
    public function clear(ClearDiscoveryCache $clear): void
    {
        $clear($this->cache);
    }

    #[EventHandler(deferred: true)]
    public function after(CommandFinished $event): void
    {
        if (in_array($event->command, ['discovery:cache', 'optimize']) && $this->cache->strategy !== DiscoveryCacheStrategy::FULL) {
            warning('Discovery caching is disabled in the current environment, cache was not generated.');
        }
    }
}
