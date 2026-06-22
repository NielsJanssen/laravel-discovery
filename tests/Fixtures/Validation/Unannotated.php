<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

/** Nothing to discover. */
final class Unannotated
{
    public string $name = 'x';

    public function run(string $input): void {}
}
