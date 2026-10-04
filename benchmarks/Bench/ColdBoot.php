<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Queries;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use PhpBench\Attributes as Bench;

#[Bench\OutputTimeUnit('milliseconds', precision: 2)]
abstract class ColdBoot
{
    use Setups;

    abstract protected function size(): Size;

    /** @param array{setup: string} $params */
    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(1)]
    #[Bench\Warmup(0)]
    #[Bench\Iterations(100)]
    public function benchBootAndQuery(array $params): void
    {
        BenchApp::execute(BenchApp::create(Setup::from($params['setup']), $this->size()), Queries::SCALAR);
    }
}
