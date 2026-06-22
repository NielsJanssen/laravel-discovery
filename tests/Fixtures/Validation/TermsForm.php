<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\AcceptedIf;
use NielsJanssen\Laravel\Validation\ValidationContext;

final class TermsForm
{
    public bool $isEuResident = false;

    /** GDPR consent must be accepted when the customer is an EU resident. */
    #[AcceptedIf(static function (ValidationContext $context): bool {
        return $context->root->isEuResident;
    })]
    public bool $gdprConsent = false;
}
