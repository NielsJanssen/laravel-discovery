<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

enum Status: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
