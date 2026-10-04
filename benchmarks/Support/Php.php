<?php

declare(strict_types=1);

namespace Benchmarks\Support;

final class Php
{
    /**
     * The ini settings every benchmark process runs with.
     *
     * @return array<string, string>
     */
    public static function benchIni(): array
    {
        return [
            'opcache.enable_cli' => '1',
            'opcache.file_cache' => Paths::opcache(),
            'opcache.jit' => 'disable',
            'memory_limit' => '-1',
        ];
    }

    /**
     * This process's values of the benchmark ini settings, so a child process runs as its parent does.
     *
     * @return array<string, string>
     */
    public static function inheritedIni(): array
    {
        $ini = [];

        foreach (array_keys(self::benchIni()) as $key) {
            $value = ini_get($key);

            if (is_string($value) && $value !== '') {
                $ini[$key] = $value;
            }
        }

        return $ini;
    }

    /**
     * Runs a PHP script in a child process and returns its exit status.
     *
     * @param array<string, string> $ini
     */
    public static function run(array $ini, string $script, string ...$arguments): int
    {
        $flags = array_map(
            static fn(string $key, string $value): string => '-d ' . escapeshellarg("{$key}={$value}"),
            array_keys($ini),
            $ini,
        );

        passthru(implode(' ', [escapeshellarg(PHP_BINARY), ...$flags, escapeshellarg($script), ...array_map(escapeshellarg(...), $arguments)]), $status);

        return $status;
    }
}
