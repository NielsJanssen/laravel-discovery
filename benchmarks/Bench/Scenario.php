<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Queries;
use PhpBench\Attributes as Bench;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\ParamProviders('setups')]
#[Bench\Warmup(3)]
#[Bench\Iterations(20)]
abstract class Scenario
{
    use WarmApp;

    #[Bench\Revs(100)]
    public function benchModelBinding(): void
    {
        $this->graphql->query(Queries::MODEL_BINDING);
    }

    #[Bench\Revs(100)]
    public function benchInvalidArgs(): void
    {
        $this->graphql->query(Queries::INVALID_ARGS);
    }

    #[Bench\Revs(100)]
    public function benchInvalidInput(): void
    {
        $this->graphql->query(Queries::INVALID_INPUT);
    }

    #[Bench\Revs(50)]
    public function benchPaginated(): void
    {
        $this->graphql->query(Queries::PAGINATED);
    }

    #[Bench\Revs(200)]
    public function benchMiddleware(): void
    {
        $this->graphql->query(Queries::MIDDLEWARE);
    }

    #[Bench\Revs(200)]
    public function benchAuthorizationHelper(): void
    {
        $this->graphql->query(Queries::AUTHORIZATION_HELPER);
    }

    #[Bench\Revs(20)]
    public function benchFactoryFields(): void
    {
        $this->graphql->query(Queries::FACTORY_FIELDS);
    }

    #[Bench\Revs(20)]
    public function benchProvidedType(): void
    {
        $this->graphql->query(Queries::PROVIDED_TYPE);
    }
}
