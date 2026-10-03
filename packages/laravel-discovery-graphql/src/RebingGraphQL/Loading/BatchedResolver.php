<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use GraphQL\Executor\Promise\Adapter\SyncPromise;
use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldBlueprint;
use RuntimeException;

/** Resolves a batched field by deferring its parent to the execution's Loaders. */
final readonly class BatchedResolver
{
    /**
     * @param  class-string<BatchLoader>  $loader
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private FieldBlueprint $field,
        private string $loader,
        private array $options,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $root, array $args, mixed $context, ?ResolveInfo $info = null): SyncPromise
    {
        if (! is_object($root)) {
            throw new RuntimeException("Cannot resolve {$this->path()}: expected an object, got " . get_debug_type($root) . '.');
        }

        $loaders = $this->field->app->make(Loaders::class);

        return $loaders->defer($this->loader, $root, $this->options, $args)->then($this->checkNull(...));
    }

    private function checkNull(mixed $value): mixed
    {
        if ($value !== null || $this->field->typeRef->nullable) {
            return $value;
        }

        throw new RuntimeException(sprintf(
            'Field %s is non-null, but %s returned null for one of its parents. Make the field nullable, or return a value for every root.',
            $this->path(),
            $this->loader,
        ));
    }

    private function path(): string
    {
        return "{$this->field->type->name}.{$this->field->field->name}";
    }
}
