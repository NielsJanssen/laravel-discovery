<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Queries;
use PhpBench\Attributes as Bench;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\ParamProviders('setups')]
#[Bench\Warmup(3)]
#[Bench\Iterations(20)]
abstract class Http
{
    use WarmApp;

    #[Bench\Revs(100)]
    public function benchHttpScalar(): void
    {
        BenchApp::post($this->app, Queries::SCALAR);
    }

    #[Bench\Revs(100)]
    public function benchHttpInputMutation(): void
    {
        BenchApp::post($this->app, Queries::INPUT_MUTATION);
    }
}
