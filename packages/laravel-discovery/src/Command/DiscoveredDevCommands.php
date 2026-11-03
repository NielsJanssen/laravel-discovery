<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Foundation\DevCommand as LaravelDevCommand;
use Illuminate\Foundation\DevCommands;

/** Registers dev commands with an explicit source and priority instead of deriving them from the call stack. */
final class DiscoveredDevCommands extends DevCommands
{
    /** Add the command unless one of the same name with a higher priority is already registered. */
    public static function add(LaravelDevCommand $command): void
    {
        $existing = static::$commands[$command->name()] ?? null;

        if (! $existing instanceof LaravelDevCommand || $command->priority() >= $existing->priority()) {
            static::$commands[$command->name()] = $command;
        }
    }

    /**
     * The priority `DevCommands::register()` gives a call made from the given file.
     *
     * @return LaravelDevCommand::PRIORITY_VENDOR|LaravelDevCommand::PRIORITY_USERLAND
     */
    public static function priority(string $file, string $vendorPath): int
    {
        return str_contains($file, $vendorPath)
            ? LaravelDevCommand::PRIORITY_VENDOR
            : LaravelDevCommand::PRIORITY_USERLAND;
    }

    /** Forget every registered dev command and filter. */
    public static function reset(): void
    {
        static::$commands = [];
        static::$only = [];
        static::$except = [];
        static::$order = [];
        static::$withoutVendorCommands = false;
        static::$withoutDefaultCommands = false;
    }
}
