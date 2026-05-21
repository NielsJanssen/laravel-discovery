<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:discovery', description: 'Make a new discovery')]
class MakeDiscoveryCommand extends GeneratorCommand
{
    protected $type = 'Discovery';

    protected function getStub(): string
    {
        return __DIR__ . '/../../stubs/Discovery.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Discoveries';
    }

    protected function qualifyClass($name): string
    {
        $class = parent::qualifyClass($name);

        if (!str_ends_with($class, $this->type)) {
            $class .= $this->type;
        }

        return $class;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the rule already exists'],
        ];
    }
}
