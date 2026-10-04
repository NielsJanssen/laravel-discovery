<?php

declare(strict_types=1);

namespace Benchmarks\Support;

enum Size: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    /** Number of generated units, each one object type, input, enum, query and mutation. */
    public function units(): int
    {
        return match ($this) {
            self::Small => 10,
            self::Medium => 50,
            self::Large => 200,
        };
    }

    public function segment(): string
    {
        return ucfirst($this->value);
    }

    public function namespace(string $setup): string
    {
        return "Acme\\Bench\\{$this->segment()}\\{$setup}";
    }
}
