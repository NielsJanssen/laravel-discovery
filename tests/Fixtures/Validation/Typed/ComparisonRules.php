<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\Different;
use NielsJanssen\Laravel\Validation\Rule\Gt;
use NielsJanssen\Laravel\Validation\Rule\Gte;
use NielsJanssen\Laravel\Validation\Rule\InArray;
use NielsJanssen\Laravel\Validation\Rule\InArrayKeys;
use NielsJanssen\Laravel\Validation\Rule\Lt;
use NielsJanssen\Laravel\Validation\Rule\Lte;
use NielsJanssen\Laravel\Validation\Rule\RequiredArrayKeys;
use NielsJanssen\Laravel\Validation\Rule\Same;

final class ComparisonRules
{
    #[Same('password')]
    public mixed $same = null;

    #[Different('username')]
    public mixed $different = null;

    #[Gt('floor')]
    public mixed $gt = null;

    #[Gte(10)]
    public mixed $gte = null;

    #[Lt('ceiling')]
    public mixed $lt = null;

    #[Lte(99.5)]
    public mixed $lte = null;

    #[InArray('allowed.*')]
    public mixed $inArray = null;

    #[InArrayKeys(['nl', 'be'])]
    public mixed $inArrayKeys = null;

    #[RequiredArrayKeys(['street', 'city'])]
    public mixed $requiredArrayKeys = null;
}
