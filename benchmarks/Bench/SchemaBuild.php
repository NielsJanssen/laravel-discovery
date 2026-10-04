<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Queries;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use PhpBench\Attributes as Bench;
use Rebing\GraphQL\GraphQL;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\ParamProviders('setups')]
#[Bench\Revs(1)]
#[Bench\Warmup(0)]
#[Bench\Iterations(20)]
abstract class SchemaBuild
{
    use Setups;

    private GraphQL $graphql;

    abstract protected function size(): Size;

    /** @param array{setup: string} $params */
    public function boot(array $params): void
    {
        $this->graphql = BenchApp::graphql(BenchApp::create(Setup::from($params['setup']), $this->size()));
    }

    public function benchSchema(): void
    {
        $this->graphql->schema();
    }

    public function benchSchemaAllTypes(): void
    {
        $this->graphql->schema()->getTypeMap();
    }

    public function benchFirstQuery(): void
    {
        $this->graphql->query(Queries::SCALAR);
    }
}
