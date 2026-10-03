<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;

final class FileLoader implements BatchLoader
{
    /** @var list<array{collection: mixed, args: array<string, mixed>, roots: list<string>}> */
    public static array $calls = [];

    public function load(array $roots, array $options, array $args): array
    {
        $names = array_map(static fn(object $root): string => $root instanceof Writer ? $root->name : $root::class, $roots);

        self::$calls[] = ['collection' => $options['collection'], 'args' => $args, 'roots' => $names];

        $suffix = isset($args['size']) && is_string($args['size']) ? ".{$args['size']}" : '';

        return array_map(static fn(string $name): array => ["{$options['collection']}/{$name}{$suffix}"], $names);
    }
}
