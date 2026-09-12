<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/**
 * Authorization for an action, in three forms:
 *
 *   #[Authorize]                          must be logged in
 *   #[Authorize(gate: SomeGate::class)]   delegate to an AuthorizationGate
 *   #[Authorize('view')]                  on a model-bound parameter: Gate check against the model
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class Authorize
{
    public function __construct(
        /** Gate ability; only meaningful on a model-bound parameter. */
        public readonly ?string $ability = null,
        /** @var class-string<AuthorizationGate>|null $gate */
        public readonly ?string $gate = null,
        public readonly ?string $message = null,
    ) {}
}
