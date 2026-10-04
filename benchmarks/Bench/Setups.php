<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Setup;
use Generator;

trait Setups
{
    /** @return Generator<string, array{setup: string}> */
    public function setups(): Generator
    {
        foreach (Setup::compared() as $setup) {
            yield $setup->value => ['setup' => $setup->value];
        }
    }

    /** @return Generator<string, array{setup: string}> */
    public function batchSetups(): Generator
    {
        yield from $this->setups();
        yield Setup::RebingEager->value => ['setup' => Setup::RebingEager->value];
    }
}
