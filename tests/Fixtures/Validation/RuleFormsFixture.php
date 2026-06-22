<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Illuminate\Validation\Rule as LaravelRule;
use NielsJanssen\Laravel\Validation\Rule\Rule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/** Every spelling #[Rule] accepts, side by side. */
final class RuleFormsFixture
{
    #[Rule(['min:10', 'max:20'])]
    public string $arrayForm = 'somewhere in range';

    #[Rule(static function (): object {
        return LaravelRule::in(['nl', 'be']);
    })]
    public string $factoryObject = 'nl';

    #[Rule(static function (): array {
        return ['min:2', 'max:4'];
    })]
    public string $factoryArray = 'abc';

    /** The factory sees the context it is being resolved for. */
    #[Rule(static function (ValidationContext $context): string {
        return 'in:' . $context->path;
    })]
    public string $contextAware = 'contextAware';

    #[Captcha]
    public string $captcha = 'ABC123';
}
