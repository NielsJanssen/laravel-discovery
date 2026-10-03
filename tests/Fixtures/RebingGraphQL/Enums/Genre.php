<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Enum;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\EnumValue;

#[Enum(description: 'Shelf a book is filed under')]
enum Genre: string
{
    case Fiction = 'fiction';
    #[EnumValue(description: 'Biographies, essays, history')]
    case NonFiction = 'non_fiction';
    #[\Deprecated('Use Fiction')]
    case Novel = 'novel';
    #[\Deprecated('Use Fiction', since: '2.0')]
    case Saga = 'saga';
}
