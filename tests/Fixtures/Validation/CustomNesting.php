<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Attribute;
use NielsJanssen\Laravel\Validation\MessageValidationRule;
use NielsJanssen\Laravel\Validation\NestedValidationRule;
use NielsJanssen\Laravel\Validation\Nesting;
use NielsJanssen\Laravel\Validation\ValidationContext;

/** A user-defined nesting attribute: found by interface, with no edit to the package. */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Countries implements NestedValidationRule
{
    public Nesting $nesting {
        get => Nesting::Each;
    }

    public array $allowedClasses {
        get => [Country::class];
    }

    public array $elementRules {
        get => [];
    }

    public function rules(ValidationContext $context): array
    {
        return [];
    }
}

/** A user-defined attribute that supplies its own message. */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Postcode implements MessageValidationRule
{
    public ?string $message {
        get => 'That is not a valid postcode.';
    }

    public ?string $messageKey {
        get => 'regex';
    }

    public function rules(ValidationContext $context): array
    {
        return ['regex:/^[0-9]{4}\s?[A-Z]{2}$/'];
    }
}

final class CustomNesting
{
    #[Countries]
    public array $visited = [];

    #[Postcode]
    public string $postcode = '1011 AB';
}
