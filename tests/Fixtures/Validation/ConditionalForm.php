<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\ProhibitedIf;
use NielsJanssen\Laravel\Validation\Rule\RequiredIf;
use NielsJanssen\Laravel\Validation\ValidationContext;

final class ConditionalForm
{
    public bool $subscribe = false;

    /** Required only when the user opted in to subscribe. */
    #[RequiredIf(static function (ValidationContext $context): bool {
        return $context->root->subscribe === true;
    })]
    public ?string $email = null;

    /** Cannot be set unless the user subscribed. */
    #[ProhibitedIf(static function (ValidationContext $context): bool {
        return $context->root->subscribe === false;
    })]
    public ?string $topic = null;
}
