<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;

/** Two annotated methods: discovery must find both, not stop at the first. */
final class MultiMethodAction
{
    public function first(#[Min(1)] string $a): void {}

    public function second(#[Min(2)] string $b): void {}

    public function unannotated(string $c): void {}
}
