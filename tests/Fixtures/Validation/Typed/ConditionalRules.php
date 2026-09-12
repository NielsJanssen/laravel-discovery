<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\AcceptedIf;
use NielsJanssen\Laravel\Validation\Rule\DeclinedIf;
use NielsJanssen\Laravel\Validation\Rule\ExcludeIf;
use NielsJanssen\Laravel\Validation\Rule\ExcludeUnless;
use NielsJanssen\Laravel\Validation\Rule\ProhibitedIf;
use NielsJanssen\Laravel\Validation\Rule\ProhibitedUnless;
use NielsJanssen\Laravel\Validation\Rule\RequiredIf;
use NielsJanssen\Laravel\Validation\Rule\RequiredUnless;

final class ConditionalRules
{
    #[RequiredIf('plan', 'pro')]
    public mixed $requiredIf = null;

    #[RequiredIf('plan', ['pro', 'team'])]
    public mixed $requiredIfAny = null;

    #[RequiredUnless('subscribe', '1')]
    public mixed $requiredUnless = null;

    #[ProhibitedIf('plan', 'free')]
    public mixed $prohibitedIf = null;

    #[ProhibitedUnless('plan', 'pro')]
    public mixed $prohibitedUnless = null;

    #[ExcludeIf('plan', 'free')]
    public mixed $excludeIf = null;

    #[ExcludeUnless('plan', 'pro')]
    public mixed $excludeUnless = null;

    #[AcceptedIf('plan', 'pro')]
    public mixed $acceptedIf = null;

    #[DeclinedIf('plan', 'free')]
    public mixed $declinedIf = null;
}
