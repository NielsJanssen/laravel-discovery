<?php

declare(strict_types=1);

namespace Benchmarks\Generator;

use Benchmarks\Support\Paths;
use Benchmarks\Support\Setup;
use Benchmarks\Support\Size;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class FixtureGenerator
{
    public function __construct(private Size $size) {}

    /** Writes every class, the discovery base path and Rebing's registration for this size; returns the file count. */
    public function generate(): int
    {
        $base = Paths::base($this->size);
        self::remove($base);

        $written = $this->writeClasses("{$base}/Discovery", $this->size->namespace('Discovery'), DiscoveryTemplates::unit(), [...DiscoveryTemplates::features(), ...DiscoveryScenarioTemplates::features()]);
        $written += $this->writeClasses("{$base}/Rebing", $this->size->namespace('Rebing'), RebingTemplates::unit(), [...RebingTemplates::features(), ...RebingScenarioTemplates::features()]);

        self::write("{$base}/rebing.php", "<?php\n\nreturn " . var_export($this->rebingRegistration(), true) . ";\n");
        self::write("{$base}/composer.json", $this->composerJson());

        if (! symlink('../../../vendor', "{$base}/vendor")) {
            throw new RuntimeException("Could not link {$base}/vendor.");
        }

        foreach (Setup::cases() as $setup) {
            foreach (['framework/cache', 'framework/views', 'logs'] as $directory) {
                self::directory(Paths::storage($this->size, $setup) . "/{$directory}");
            }
        }

        return $written;
    }

    /**
     * @param array<string, string> $unit
     * @param array<string, string> $features
     */
    private function writeClasses(string $directory, string $namespace, array $unit, array $features): int
    {
        $header = "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\n";
        $written = 0;

        for ($i = 1; $i <= $this->size->units(); $i++) {
            foreach ($unit as $name => $body) {
                $replace = ['__I__' => (string) $i];
                self::write("{$directory}/" . strtr($name, $replace) . '.php', $header . strtr($body, $replace) . "\n");
                $written++;
            }
        }

        foreach ($features as $name => $body) {
            self::write("{$directory}/{$name}.php", $header . $body . "\n");
            $written++;
        }

        return $written;
    }

    /** @return array{types: array<string, string>, query: array<string, string>, mutation: array<string, string>, eager: array<string, string>} */
    private function rebingRegistration(): array
    {
        $namespace = $this->size->namespace('Rebing');
        $registration = RebingTemplates::registration($namespace, $this->size->units());
        $scenarios = RebingScenarioTemplates::registration($namespace);

        foreach (['types', 'query', 'mutation'] as $key) {
            $registration[$key] = [...$registration[$key], ...$scenarios[$key]];
        }

        return $registration;
    }

    private function composerJson(): string
    {
        $packages = '../../../packages';

        return json_encode([
            'autoload' => [
                'psr-4' => [
                    $this->size->namespace('Discovery') . '\\' => 'Discovery/',
                    'NielsJanssen\\Laravel\\Discovery\\' => "{$packages}/laravel-discovery/src/",
                    'NielsJanssen\\Laravel\\Discovery\\RebingGraphQL\\' => "{$packages}/laravel-discovery-graphql/src/RebingGraphQL/",
                    'NielsJanssen\\Laravel\\Validation\\' => "{$packages}/laravel-validation/src/",
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private static function write(string $path, string $contents): void
    {
        self::directory(dirname($path));

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Could not write {$path}.");
        }
    }

    private static function directory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0o755, true) && ! is_dir($path)) {
            throw new RuntimeException("Could not create {$path}.");
        }
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
