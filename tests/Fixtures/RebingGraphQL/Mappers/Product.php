<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Product
{
    public function __construct(
        public int $id,
        public Money $price,
        public ?Money $discount = null,
    ) {}

    #[Field]
    public function total(#[Arg] Money $shipping): Money
    {
        return new Money($this->price->cents + $shipping->cents, $this->price->currency);
    }

    #[Field]
    public function related(?string $id = null): string
    {
        return $id ?? 'none';
    }
}
