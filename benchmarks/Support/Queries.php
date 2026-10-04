<?php

declare(strict_types=1);

namespace Benchmarks\Support;

final class Queries
{
    public const string SCALAR = '{ greet(name: "Acme") }';

    public const string NESTED = '{ volumes(count: 100) { id title pages ratio available genre tags author { name country } chapters { number title } } }';

    public const string VALIDATED = '{ search(term: "acme", limit: 10) }';

    public const string INPUT_MUTATION = 'mutation { createVolume(input: {title: "Acme Handbook", pages: 320, genre: Poetry, tags: ["acme", "guide"], author: {name: "Acme Author", country: "NL"}}) { id title pages ratio available genre tags author { name country } chapters { number } } }';

    public const string ENUM = '{ genres(after: Fiction, count: 20) }';

    public const string AUTHORIZED = '{ accounts(count: 100) { number holder balance } }';

    public const string BATCH = '{ authors { id name books { id title pages } } }';

    /** @return array<string, string> */
    public static function features(): array
    {
        return [
            'scalar' => self::SCALAR,
            'nested' => self::NESTED,
            'validated' => self::VALIDATED,
            'input_mutation' => self::INPUT_MUTATION,
            'enum' => self::ENUM,
            'authorized' => self::AUTHORIZED,
            'batch' => self::BATCH,
        ];
    }

    /**
     * The generated filler operations of the first and last unit, checked by the guard.
     *
     * @return array<string, string>
     */
    public static function filler(Size $size): array
    {
        $queries = [];

        foreach (array_unique([1, $size->units()]) as $unit) {
            $queries["item{$unit}"] = "{ item{$unit}(id: \"acme\") { id name rank weight active level tags } }";
            $queries["createItem{$unit}"] = "mutation { createItem{$unit}(input: {name: \"Acme\", rank: 3, level: High, tags: [\"x\"]}) { id name rank weight active level tags } }";
        }

        return $queries;
    }
}
