<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\BenchApp;
use Benchmarks\Support\Database;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use Illuminate\Foundation\Application;
use Rebing\GraphQL\GraphQL;

trait WarmApp
{
    use Setups;

    private Application $app;

    private GraphQL $graphql;

    abstract protected function size(): Size;

    /** @param array{setup: string} $params */
    public function boot(array $params): void
    {
        $this->app = BenchApp::create(Setup::from($params['setup']), $this->size());
        Database::seed($this->app);
        $this->graphql = BenchApp::graphql($this->app);
    }
}
