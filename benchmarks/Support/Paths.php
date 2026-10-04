<?php

declare(strict_types=1);

namespace Benchmarks\Support;

final class Paths
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function generated(): string
    {
        return self::root() . '/benchmarks/.generated';
    }

    /** The per-size base path that discovery reads composer.json from. */
    public static function base(Size $size): string
    {
        return self::generated() . '/' . $size->segment();
    }

    public static function storage(Size $size, Setup $setup): string
    {
        return self::base($size) . '/storage/' . $setup->value;
    }

    public static function opcache(): string
    {
        return self::generated() . '/opcache';
    }
}
