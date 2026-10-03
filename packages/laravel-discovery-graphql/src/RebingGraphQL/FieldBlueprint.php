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

    /** Rebing's `privacy`: the field resolves to null without running the resolver when any check fails. */
    public ?Closure $privacy {
        get => $this->checks === [] ? null : $this->allows(...);
    }

    public function __construct(
        public readonly Application $app,
        public readonly DiscoveredType $type,
        public readonly DiscoveredTypeField $field,
        public private(set) TypeRef $typeRef,
        public private(set) Closure $resolver,
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
        $this->resolver = new WrappedResolver($wrapper, $this->resolver)(...);
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
