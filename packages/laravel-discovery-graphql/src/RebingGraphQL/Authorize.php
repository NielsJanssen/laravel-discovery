<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Authorize
{
    public function __construct(
        /** @var class-string<AuthorizationGate>|null */
        public readonly ?string $gate = null,
        public readonly ?string $message = null,
    ) {}
}
