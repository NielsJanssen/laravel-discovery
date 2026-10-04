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

    public const string MODEL_BINDING = '{ author(id: 7) { id name } }';

    public const string INVALID_ARGS = 'mutation { renameVolume(title: "A", edition: 500) }';

    public const string INVALID_INPUT = 'mutation { createVolume(input: {title: "A", pages: 0, genre: Drama, tags: [], author: {name: "B", country: "NLD"}}) { id } }';

    public const string PAGINATED = '{ bookPage(page: 2, limit: 25) { data { id title pages } total per_page current_page from to last_page has_more_pages } }';

    public const string MIDDLEWARE = '{ shout(text: "acme") }';

    public const string AUTHORIZATION_HELPER = '{ vault(number: 42) }';

    public const string FACTORY_FIELDS = '{ gadgets(count: 100) { name width height depth weight } }';

    public const string PROVIDED_TYPE = '{ readings(count: 100) { sensor value unit } }';

    /** @var list<int> */
    public const array PAYLOAD_ITEMS = [10, 100, 1000];

    /** The nested list operation for a number of items. */
    public static function nested(int $items): string
    {
        return str_replace('count: 100', "count: {$items}", self::NESTED);
    }

    /**
     * Every successful operation the guard compares, keyed by name.
     *
     * @return array<string, string>
     */
    public static function successes(): array
    {
        $payloads = [];

        foreach (self::PAYLOAD_ITEMS as $items) {
            $payloads["nested_{$items}"] = self::nested($items);
        }

        return [
            'scalar' => self::SCALAR,
            'nested' => self::NESTED,
            'validated' => self::VALIDATED,
            'input_mutation' => self::INPUT_MUTATION,
            'enum' => self::ENUM,
            'authorized' => self::AUTHORIZED,
            'batch' => self::BATCH,
            'model_binding' => self::MODEL_BINDING,
            'paginated' => self::PAGINATED,
            'middleware' => self::MIDDLEWARE,
            'authorization_helper' => self::AUTHORIZATION_HELPER,
            'factory_fields' => self::FACTORY_FIELDS,
            'provided_type' => self::PROVIDED_TYPE,
            ...$payloads,
        ];
    }

    /**
     * The operations that must fail validation, keyed by name.
     *
     * @return array<string, string>
     */
    public static function failures(): array
    {
        return [
            'invalid_args' => self::INVALID_ARGS,
            'invalid_input' => self::INVALID_INPUT,
        ];
    }

    /**
     * The operations whose SQL queries the guard counts, keyed by name.
     *
     * @return array<string, string>
     */
    public static function counted(): array
    {
        return [
            'batch' => self::BATCH,
            'model_binding' => self::MODEL_BINDING,
            'paginated' => self::PAGINATED,
        ];
    }

    /**
     * The operations sent as a full HTTP request, keyed by name.
     *
     * @return array<string, string>
     */
    public static function http(): array
    {
        return [
            'http_scalar' => self::SCALAR,
            'http_input_mutation' => self::INPUT_MUTATION,
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
