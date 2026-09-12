<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use DateTimeImmutable;
use NielsJanssen\Laravel\Validation\Rule\After;
use NielsJanssen\Laravel\Validation\Rule\AfterOrEqual;
use NielsJanssen\Laravel\Validation\Rule\Before;
use NielsJanssen\Laravel\Validation\Rule\BeforeOrEqual;
use NielsJanssen\Laravel\Validation\Rule\Date;
use NielsJanssen\Laravel\Validation\Rule\DateEquals;
use NielsJanssen\Laravel\Validation\Rule\DateFormat;

final class DateRules
{
    #[Date]
    public mixed $date = null;

    #[DateFormat(['Y-m-d', 'd-m-Y'])]
    public mixed $format = null;

    /** A DateTimeInterface is written out in the format Laravel parses back. */
    #[After(new DateTimeImmutable('2026-01-02 03:04:05'))]
    public mixed $after = null;

    #[AfterOrEqual('today')]
    public mixed $afterOrEqual = null;

    #[Before('ends_at')]
    public mixed $before = null;

    #[BeforeOrEqual('tomorrow')]
    public mixed $beforeOrEqual = null;

    #[DateEquals('starts_at')]
    public mixed $equals = null;
}
