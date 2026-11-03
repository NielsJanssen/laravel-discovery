<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/** A command method parameter and the console input it reads from. */
final readonly class InputParameter
{
    public function __construct(
        public string $parameterName,
        public InputArgument|InputOption $input,
    ) {}

    public function inputName(): string
    {
        return $this->input->getName();
    }
}
