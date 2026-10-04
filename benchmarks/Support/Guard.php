<?php

declare(strict_types=1);

namespace Benchmarks\Support;

use GraphQL\Utils\SchemaPrinter;
use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryConfig;

final class Guard
{
    /** @var list<string> */
    private array $failures = [];

    /** @var array<string, array<string, int>> */
    public private(set) array $queryCounts = [];

    public function __construct(private readonly Size $size) {}

    /**
     * Checks that every setup of this size serves the same schema and the same responses.
     *
     * @return list<string> the failures, empty when the setups are equivalent
     */
    public function check(): array
    {
        $results = [];

        foreach ([Setup::Cached, Setup::Rebing, Setup::RebingEager, Setup::Uncached] as $setup) {
            $app = BenchApp::create($setup, $this->size);
            $this->checkProviders($app, $setup);
            $this->checkCache($app, $setup);
            Database::seed($app);

            $results[$setup->value] = [
                'sdl' => self::normalisedSdl($app),
                'responses' => $this->responses($app),
            ];

            foreach (Queries::counted() as $name => $query) {
                $this->queryCounts[$name][$setup->value] = Database::countQueries($app, static fn() => BenchApp::execute($app, $query));
            }
        }

        $reference = $results[Setup::Rebing->value];

        foreach ($results as $setup => $result) {
            if ($result['sdl'] !== $reference['sdl']) {
                $this->failures[] = "{$this->size->value}/{$setup} prints a different SDL than rebing";
            }

            foreach ($reference['responses'] as $name => $response) {
                if ($result['responses'][$name] !== $response) {
                    $this->failures[] = "{$this->size->value}/{$setup} answers {$name} differently than rebing";
                }
            }
        }

        foreach ($reference['responses'] as $name => $response) {
            $failed = str_contains($response, '"errors"') || ! str_contains($response, '"data"');
            $expectsFailure = array_key_exists($name, Queries::failures());

            if ($failed !== $expectsFailure || ($expectsFailure && ! str_contains($response, '"category":"validation"'))) {
                $this->failures[] = "{$this->size->value}: {$name} " . ($expectsFailure ? 'does not fail validation' : 'does not succeed') . ': ' . substr($response, 0, 200);
            }
        }

        return $this->failures;
    }

    /** The SDL with its types and its root fields sorted. */
    public static function normalisedSdl(Application $app): string
    {
        $blocks = explode("\n\n", SchemaPrinter::doPrint(BenchApp::graphql($app)->schema(), ['sortTypes' => true]));

        foreach ($blocks as $index => $block) {
            if (str_starts_with($block, 'type Query {') || str_starts_with($block, 'type Mutation {')) {
                $lines = explode("\n", $block);
                $fields = array_slice($lines, 1, -1);
                sort($fields);
                $blocks[$index] = implode("\n", [$lines[0], ...$fields, end($lines)]);
            }
        }

        return implode("\n\n", $blocks);
    }

    /** @return array<string, string> */
    private function responses(Application $app): array
    {
        $responses = [];

        foreach ([...Queries::successes(), ...Queries::failures(), ...Queries::filler($this->size)] as $name => $query) {
            $responses[$name] = json_encode(BenchApp::execute($app, $query), JSON_THROW_ON_ERROR);
        }

        foreach (Queries::http() as $name => $query) {
            $response = BenchApp::post($app, $query);
            $responses[$name] = $response->getStatusCode() . ' ' . $response->getContent();
        }

        return $responses;
    }

    private function checkProviders(Application $app, Setup $setup): void
    {
        $loaded = array_keys($app->getLoadedProviders());

        if (in_array(DiscoveryServiceProvider::class, $loaded, true) !== $setup->usesDiscovery()) {
            $this->fail($setup, $setup->usesDiscovery() ? 'does not load discovery' : 'loads discovery');
        }

        foreach ($loaded as $provider) {
            if (str_starts_with($provider, 'Workbench\\')) {
                $this->fail($setup, "loads {$provider}");
            }
        }
    }

    private function checkCache(Application $app, Setup $setup): void
    {
        if (! $setup->usesDiscovery()) {
            return;
        }

        $cache = $app->make(DiscoveryCache::class);

        if ($cache->enabled !== ($setup === Setup::Cached)) {
            $this->fail($setup, $cache->enabled ? 'reads the discovery cache' : 'does not read the discovery cache');
        }

        $locations = $app->make(DiscoveryConfig::class)->locations;
        $scanned = array_filter($locations, fn($location): bool => $location->path === Paths::base($this->size) . '/Discovery');

        if (count($scanned) !== 1) {
            $this->fail($setup, 'does not scan exactly its own generated classes');
        }

        foreach ($locations as $location) {
            if (str_contains($location->path, '/.generated/') && ! str_starts_with($location->path, Paths::base($this->size) . '/')) {
                $this->fail($setup, "scans {$location->path}");
            }
        }

        if ($setup !== Setup::Cached) {
            return;
        }

        foreach ($locations as $location) {
            if ($cache->restore($location) === null) {
                $this->fail($setup, "has no cache for {$location->path}");
            }
        }

        if (class_exists($this->size->namespace('Discovery') . '\\Item1', false)) {
            $this->fail($setup, 'loaded the generated classes, so discovery scanned them');
        }
    }

    private function fail(Setup $setup, string $message): void
    {
        $this->failures[] = "{$this->size->value}/{$setup->value} {$message}";
    }
}
