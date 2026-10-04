<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Queries;
use PhpBench\Attributes as Bench;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\Warmup(3)]
#[Bench\Iterations(20)]
abstract class WarmExecution
{
    use WarmApp;

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(200)]
    public function benchScalar(): void
    {
        $this->graphql->query(Queries::SCALAR);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(100)]
    public function benchValidatedArgs(): void
    {
        $this->graphql->query(Queries::VALIDATED);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(100)]
    public function benchInputMutation(): void
    {
        $this->graphql->query(Queries::INPUT_MUTATION);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(200)]
    public function benchEnum(): void
    {
        $this->graphql->query(Queries::ENUM);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(20)]
    public function benchAuthorizedFields(): void
    {
        $this->graphql->query(Queries::AUTHORIZED);
    }

    #[Bench\ParamProviders('batchSetups')]
    #[Bench\Revs(10)]
    public function benchBatchLoading(): void
    {
        $this->graphql->query(Queries::BATCH);
    }
}
