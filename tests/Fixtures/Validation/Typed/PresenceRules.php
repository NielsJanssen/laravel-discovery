<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\ExcludeWithout;
use NielsJanssen\Laravel\Validation\Rule\Filled;
use NielsJanssen\Laravel\Validation\Rule\MissingIf;
use NielsJanssen\Laravel\Validation\Rule\MissingWith;
use NielsJanssen\Laravel\Validation\Rule\Present;
use NielsJanssen\Laravel\Validation\Rule\PresentIf;
use NielsJanssen\Laravel\Validation\Rule\PresentWithAll;
use NielsJanssen\Laravel\Validation\Rule\Prohibits;
use NielsJanssen\Laravel\Validation\Rule\Required;
use NielsJanssen\Laravel\Validation\Rule\RequiredIfAccepted;
use NielsJanssen\Laravel\Validation\Rule\RequiredWith;
use NielsJanssen\Laravel\Validation\Rule\RequiredWithAll;
use NielsJanssen\Laravel\Validation\Rule\RequiredWithout;
use NielsJanssen\Laravel\Validation\Rule\RequiredWithoutAll;

final class PresenceRules
{
    #[Required]
    public mixed $required = null;

    #[Present]
    public mixed $present = null;

    #[Filled]
    public mixed $filled = null;

    #[RequiredWith(['first', 'last'])]
    public mixed $requiredWith = null;

    #[RequiredWithAll(['first', 'last'])]
    public mixed $requiredWithAll = null;

    #[RequiredWithout(['first'])]
    public mixed $requiredWithout = null;

    #[RequiredWithoutAll(['first', 'last'])]
    public mixed $requiredWithoutAll = null;

    #[RequiredIfAccepted('terms')]
    public mixed $requiredIfAccepted = null;

    #[PresentWithAll(['first', 'last'])]
    public mixed $presentWithAll = null;

    #[PresentIf('plan', ['pro', 'team'])]
    public mixed $presentIf = null;

    #[MissingWith(['legacy'])]
    public mixed $missingWith = null;

    #[MissingIf('plan', 'free')]
    public mixed $missingIf = null;

    #[ExcludeWithout(['parent'])]
    public mixed $excludeWithout = null;

    #[Prohibits(['other'])]
    public mixed $prohibits = null;
}
