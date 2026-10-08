<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Testing;

use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;

trait WithCachedDiscovery
{
    /**
     * After discovering once, every later boot in this process restores the result instead of scanning.
     */
    protected function setUpWithCachedDiscovery(): void
    {
        $provider = $this->app?->getProvider(DiscoveryServiceProvider::class);

        if ($provider instanceof DiscoveryServiceProvider) {
            $provider->warmMemoryCache();
        }
    }
}
