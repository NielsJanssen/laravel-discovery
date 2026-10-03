<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ProductQuery
{
    #[Query]
    public function product(string $id): Product
    {
        return new Product((int) $id, new Money(1250, 'EUR'));
    }

    #[Query]
    public function price(#[Arg] Money $amount): Money
    {
        return $amount;
    }

    #[Query]
    public function cheapest(): ?Money
    {
        return null;
    }

    #[Mutation]
    public function discount(#[Arg] Money $amount, ?string $id = null): string
    {
        return $amount->format() . ' off ' . ($id ?? 'everything');
    }
}
