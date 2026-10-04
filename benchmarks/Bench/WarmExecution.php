<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Database;
use Benchmarks\Support\Queries;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use PhpBench\Attributes as Bench;
use Rebing\GraphQL\GraphQL;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\Warmup(2)]
#[Bench\Iterations(10)]
abstract class WarmExecution
{
    use Setups;

    private GraphQL $graphql;

    abstract protected function size(): Size;

    /** @param array{setup: string} $params */
    public function boot(array $params): void
    {
        $app = BenchApp::create(Setup::from($params['setup']), $this->size());
        Database::seed($app);
        $this->graphql = BenchApp::graphql($app);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(200)]
    public function benchScalar(): void
    {
        $this->graphql->query(Queries::SCALAR);
    }

    #[Bench\ParamProviders('setups')]
    #[Bench\Revs(10)]
    public function benchNestedList(): void
    {
        $this->graphql->query(Queries::NESTED);
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
