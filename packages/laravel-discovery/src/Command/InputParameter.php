<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use BackedEnum;
use DateTimeInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/** A command method parameter and the console input it reads from. */
final readonly class InputParameter
{
    public function __construct(
        public string $parameterName,
        public InputArgument|InputOption $input,
        /** @var class-string<BackedEnum>|null */
        public ?string $enum = null,
        /** @var class-string<DateTimeInterface>|null */
        public ?string $date = null,
        public ?string $format = null,
        public bool $nullable = false,
        public bool $hasDefault = false,
        public mixed $default = null,
    ) {}

    public function inputName(): string
    {
        return $this->input->getName();
    }
}
