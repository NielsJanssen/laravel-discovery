<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Illuminate\Foundation\DevCommand as LaravelDevCommand;

final readonly class DiscoveredDevCommand
{
    public function __construct(
        public string $command,
        public string $name,
        public ?string $color,
        public string $file,
        public int $line,
        public string $class,
        public string $function,
    ) {}

    public static function fromAttribute(
        DevCommand $attribute,
        string $artisanCommand,
        string $file,
        int $line,
        string $class,
        string $function,
    ): self {
        $command = trim("{$artisanCommand} {$attribute->arguments}");

        return new self(
            command: $command,
            name: $attribute->name ?? LaravelDevCommand::nameFromCommand($command),
            color: $attribute->color?->value,
            file: $file,
            line: $line,
            class: $class,
            function: $function,
        );
    }

    public function toLaravel(string $vendorPath): LaravelDevCommand
    {
        $command = new LaravelDevCommand(
            command: "php artisan {$this->command}",
            source: ['file' => $this->file, 'line' => $this->line, 'class' => $this->class, 'function' => $this->function],
            name: $this->name,
            priority: DiscoveredDevCommands::priority($this->file, $vendorPath),
        );

        return $this->color === null ? $command : $command->color($this->color);
    }
}
