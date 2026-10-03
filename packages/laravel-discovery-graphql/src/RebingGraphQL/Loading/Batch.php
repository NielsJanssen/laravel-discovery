<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Illuminate\Contracts\Container\Container;
use LogicException;
use Throwable;

/** The roots collected for one loader, options and args, loaded together on first use. */
final class Batch
{
    /** @var list<object> */
    private array $roots = [];

    /** @var array<int, int> root position by spl_object_id */
    private array $positions = [];

    /** @var list<mixed>|null */
    private ?array $results = null;

    /** What the loader threw, if it failed. */
    private ?Throwable $failure = null;

    /**
     * @param  class-string<BatchLoader>  $loader
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $args
     */
    public function __construct(
        public readonly string $loader,
        public readonly array $options,
        public readonly array $args,
    ) {}

    /** Adds a root once and returns its position. */
    public function add(object $root): int
    {
        $id = spl_object_id($root);

        if (! isset($this->positions[$id])) {
            $this->positions[$id] = count($this->roots);
            $this->roots[] = $root;
        }

        return $this->positions[$id];
    }

    public function result(int $position, Container $container): mixed
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        try {
            $this->results ??= $this->load($container);
        } catch (Throwable $failure) {
            throw $this->failure = $failure;
        }

        return $this->results[$position];
    }

    /**
     * @return list<mixed>
     */
    private function load(Container $container): array
    {
        $loader = $container->make($this->loader);

        if (! $loader instanceof BatchLoader) {
            throw new LogicException(sprintf('%s does not implement %s.', $this->loader, BatchLoader::class));
        }

        $results = $loader->load($this->roots, $this->options, $this->args);
        $keys = array_keys($results);

        if ($keys !== array_keys($this->roots)) {
            throw new LogicException(sprintf(
                '%s::load() returned %s for %d roots. Return a list with one result per root, in the order of the roots.',
                $this->loader,
                count($results) === count($this->roots) ? 'an array keyed ' . implode(', ', $keys) : count($results) . ' results',
                count($this->roots),
            ));
        }

        return $results;
    }
}
