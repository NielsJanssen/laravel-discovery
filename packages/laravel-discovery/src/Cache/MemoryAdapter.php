<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Cache;

use Closure;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryLocation;

/**
 * A discovery cache that lives as long as the PHP process.
 *
 * The instance is held on the class rather than in the container, so it survives the application
 * being rebuilt: a test suite scans on its first boot and restores on every boot after that, while
 * a new process always starts empty.
 */
final class MemoryAdapter extends ArrayAdapter
{
    private static ?self $instance = null;

    private bool $warm = false;

    /** @var array<string, DiscoveryLocation[]> */
    private array $locations = [];

    public static function forProcess(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Drop the cached run, so a test that changes what discovery should find can start over.
     */
    public static function forget(): void
    {
        self::$instance = null;
    }

    public function isWarm(): bool
    {
        return $this->warm;
    }

    public function markWarm(): void
    {
        $this->warm = true;
    }

    /**
     * The discovery locations for the given autoload path, resolved once per process.
     *
     * @param Closure(): DiscoveryLocation[] $resolve
     *
     * @return DiscoveryLocation[]
     */
    public function locations(string $autoload, Closure $resolve): array
    {
        return $this->locations[$autoload] ??= $resolve();
    }
}
