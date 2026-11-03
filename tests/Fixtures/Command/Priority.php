<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

enum Priority: int
{
    case Low = 1;
    case High = 2;
}
