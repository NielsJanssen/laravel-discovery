<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Illuminate\Support\Str;
use NielsJanssen\Laravel\Validation\StringRule;

use function class_basename;

/**
 * Base for the type rules the inferrers add. The `Type` suffix exists only to dodge PHP's
 * reserved words (`string`, `array`), so it is stripped from the rule name.
 */
abstract class Type extends StringRule
{
    public string $name {
        get => Str::snake(Str::replaceEnd('Type', '', class_basename(static::class)));
    }
}
