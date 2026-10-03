<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

final readonly class Money
{
    public function __construct(
        public int $cents,
        public string $currency,
    ) {}

    public static function parse(string $value): self
    {
        [$amount, $currency] = explode(' ', $value, 2) + [1 => 'EUR'];

        return new self((int) round((float) $amount * 100), $currency);
    }

    public function format(): string
    {
        return sprintf('%.2f %s', $this->cents / 100, $this->currency);
    }
}
