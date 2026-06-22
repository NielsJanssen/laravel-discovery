<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * A plan plus values: exactly the three arrays Laravel's validator factory takes.
 */
final readonly class CompiledRules
{
    public function __construct(
        /** @var array<array-key, mixed> keyed by member name, or by element key inside an iterable */
        public array $data,

        /** @var array<string, array<int, mixed>> keyed by (dotted) path */
        public array $rules,

        /** @var array<string, string> */
        public array $messages,
    ) {}
}
