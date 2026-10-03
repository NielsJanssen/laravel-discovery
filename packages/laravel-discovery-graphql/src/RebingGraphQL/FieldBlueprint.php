<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Foundation\Application;

/** The mutable definition of a #[Type] field while its decorators run. */
final class FieldBlueprint
{
    /** @var list<Closure(mixed, array<string, mixed>, mixed, ResolveInfo): bool> */
    private array $checks = [];

    /** @var list<Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo, Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo): mixed): mixed> */
    private array $wrappers = [];

    /** Rebing's `privacy`: the field resolves to null without running the resolver when any check fails. */
    public ?Closure $privacy {
        get => $this->checks === [] ? null : $this->allows(...);
    }

    /** The source resolver inside every wrapResolver() layer, the last-added layer outermost. */
    public Closure $resolver {
        get {
            $resolver = $this->source;

            foreach ($this->wrappers as $wrapper) {
                $resolver = new WrappedResolver($wrapper, $resolver)(...);
            }

            return $resolver;
        }
    }

    public function __construct(
        public readonly Application $app,
        public readonly DiscoveredType $type,
        public readonly DiscoveredTypeField $field,
        public private(set) TypeRef $typeRef,
        private Closure $source,
    ) {}

    public function nullable(): void
    {
        $this->typeRef = $this->typeRef->asNullable();
    }

    /**
     * @param  Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo, Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo): mixed): mixed  $wrapper
     */
    public function wrapResolver(Closure $wrapper): void
    {
        $this->wrappers[] = $wrapper;
    }

    /**
     * Replaces where the value comes from; every wrapResolver() layer still runs around it, whatever the order.
     *
     * @param  Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo): mixed  $resolver
     */
    public function resolveWith(Closure $resolver): void
    {
        $this->source = $resolver;
    }

    /**
     * @param  Closure(mixed, array<string, mixed>, mixed, ResolveInfo): bool  $check
     */
    public function addPrivacy(Closure $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function allows(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        foreach ($this->checks as $check) {
            if (! $check($root, $args, $context, $info)) {
                return false;
            }
        }

        return true;
    }
}
