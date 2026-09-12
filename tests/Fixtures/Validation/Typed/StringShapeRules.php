<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\Alpha;
use NielsJanssen\Laravel\Validation\Rule\AlphaDash;
use NielsJanssen\Laravel\Validation\Rule\AlphaNum;
use NielsJanssen\Laravel\Validation\Rule\Ascii;
use NielsJanssen\Laravel\Validation\Rule\Confirmed;
use NielsJanssen\Laravel\Validation\Rule\Distinct;
use NielsJanssen\Laravel\Validation\Rule\DoesntEndWith;
use NielsJanssen\Laravel\Validation\Rule\DoesntStartWith;
use NielsJanssen\Laravel\Validation\Rule\Encoding;
use NielsJanssen\Laravel\Validation\Rule\EndsWith;
use NielsJanssen\Laravel\Validation\Rule\HexColor;
use NielsJanssen\Laravel\Validation\Rule\Ip;
use NielsJanssen\Laravel\Validation\Rule\NotRegex;
use NielsJanssen\Laravel\Validation\Rule\Regex;
use NielsJanssen\Laravel\Validation\Rule\StartsWith;
use NielsJanssen\Laravel\Validation\Rule\Timezone;
use NielsJanssen\Laravel\Validation\Rule\Url;
use NielsJanssen\Laravel\Validation\Rule\Uuid;

final class StringShapeRules
{
    #[Regex('/^[a-z]+$/')]
    public mixed $regex = null;

    #[NotRegex('/\d/')]
    public mixed $notRegex = null;

    #[StartsWith(['nl-', 'be-'])]
    public mixed $startsWith = null;

    #[DoesntStartWith(['de-'])]
    public mixed $doesntStartWith = null;

    #[EndsWith(['.nl'])]
    public mixed $endsWith = null;

    #[DoesntEndWith(['.test'])]
    public mixed $doesntEndWith = null;

    #[Encoding('UTF-8')]
    public mixed $encoding = null;

    /** A flag becomes the token Laravel reads, not `true`. */
    #[Alpha(ascii: true)]
    public mixed $alpha = null;

    #[AlphaDash]
    public mixed $alphaDash = null;

    #[AlphaNum(ascii: true)]
    public mixed $alphaNum = null;

    #[Ascii]
    public mixed $ascii = null;

    #[Distinct(strict: true, ignoreCase: true)]
    public mixed $distinct = null;

    #[Confirmed]
    public mixed $confirmed = null;

    #[Confirmed('repeat_password')]
    public mixed $confirmedField = null;

    #[Uuid(4)]
    public mixed $uuid = null;

    #[Timezone('per_country', 'NL')]
    public mixed $timezone = null;

    #[HexColor]
    public mixed $hexColor = null;

    #[Ip]
    public mixed $ip = null;

    #[Url(['https'])]
    public mixed $url = null;
}
