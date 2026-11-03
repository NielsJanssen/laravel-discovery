<?php

declare(strict_types=1);

namespace Tests\Fixtures\Command;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
