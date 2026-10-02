<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

enum Position: string
{
    case Input = 'input';
    case Output = 'output';
}
