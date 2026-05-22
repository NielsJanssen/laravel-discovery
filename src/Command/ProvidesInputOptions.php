<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

interface ProvidesInputOptions
{
    /** @return list<InputOption|InputArgument> */
    public function getOptions(): array;
}
