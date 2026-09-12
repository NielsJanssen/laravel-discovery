<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\Contains;
use NielsJanssen\Laravel\Validation\Rule\DoesntContain;
use NielsJanssen\Laravel\Validation\Rule\In;
use NielsJanssen\Laravel\Validation\Rule\NotIn;
use Tests\Fixtures\Validation\Status;

final class SetRules
{
    #[In(['nl', 'be'])]
    public mixed $in = null;

    /** A value holding a comma survives because the rule object quotes it. */
    #[In(['one, two', 'three'])]
    public mixed $quoted = null;

    #[In([Status::Draft, Status::Open])]
    public mixed $enumValues = null;

    #[NotIn(['de'])]
    public mixed $notIn = null;

    #[Contains(['nl'])]
    public mixed $contains = null;

    #[DoesntContain(['de'])]
    public mixed $doesntContain = null;
}
