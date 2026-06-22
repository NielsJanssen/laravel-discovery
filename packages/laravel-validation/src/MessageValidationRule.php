<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * A validation attribute that supplies its own error message. #[Rule(message:)] is the built-in
 * one; implement this on your own attribute to give it a message without going through the
 * `$messages` array on the validator entry points.
 */
interface MessageValidationRule extends ValidationRule
{
    /** The message, or null to leave Laravel's default in place. */
    public ?string $message { get; }

    /**
     * The suffix Laravel keys the message under — 'min' for 'min:5' — or null to key it on the
     * field path itself, which is what a rule object or a factory needs.
     */
    public ?string $messageKey { get; }
}
