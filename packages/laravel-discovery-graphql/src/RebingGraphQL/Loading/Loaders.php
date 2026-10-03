<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use GraphQL\Deferred;
use Illuminate\Contracts\Container\Container;
use LogicException;
use Throwable;

/** The pending batches of one GraphQL execution. */
final class Loaders
{
    /** @var array<string, Batch> batches that have not loaded yet, by batch key */
    private array $pending = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Defers a root to the batch for this loader, options and args; the batch loads once, when first awaited.
     *
     * @param  class-string<BatchLoader>  $loader
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $args
     */
    public function defer(string $loader, object $root, array $options = [], array $args = []): Deferred
    {
        $key = $this->key($loader, $options, $args);
        $batch = $this->pending[$key] ??= new Batch($loader, $options, $args);
        $position = $batch->add($root);

        return new Deferred(function () use ($key, $batch, $position): mixed {
            if (($this->pending[$key] ?? null) === $batch) {
                unset($this->pending[$key]);
            }

            return $batch->result($position, $this->container);
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $args
     */
    private function key(string $loader, array $options, array $args): string
    {
        try {
            return $loader . ':' . hash('xxh128', serialize([$options, $args]));
        } catch (Throwable $exception) {
            throw new LogicException("The options for $loader cannot be serialized into a batch key: {$exception->getMessage()}", previous: $exception);
        }
    }
}
