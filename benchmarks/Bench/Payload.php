<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Queries;
use Generator;
use PhpBench\Attributes as Bench;

#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
#[Bench\BeforeMethods('boot')]
#[Bench\Warmup(3)]
#[Bench\Iterations(20)]
abstract class Payload
{
    use WarmApp;

    /** @return Generator<string, array{items: int}> */
    public function items(): Generator
    {
        foreach (Queries::PAYLOAD_ITEMS as $items) {
            yield "{$items} items" => ['items' => $items];
        }
    }

    /** @param array{setup: string, items: int} $params */
    #[Bench\ParamProviders(['setups', 'items'])]
    #[Bench\Revs(10)]
    public function benchNestedList(array $params): void
    {
        $this->graphql->query(Queries::nested($params['items']));
    }
}
