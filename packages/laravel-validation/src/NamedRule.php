<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Illuminate\Support\Str;

use function class_basename;

/**
 * Base for every attribute that stands for one Laravel rule by name. Each subclass declares a typed
 * constructor for its own arguments and takes `?string $message = null` last, so a custom message is
 * available on every attribute without wrapping it in anything.
 *
 * Build Laravel rule objects inside rules(), never in the constructor: the attribute instance goes into
 * the discovery cache, so it has to stay serializable, and a closure-backed option (a Unique `ignore`,
 * say) has to be resolved against the ValidationContext of the call.
 */
abstract class NamedRule implements MessageValidationRule
{
    public function __construct(
        public readonly ?string $message = null,
    ) {}

    /** The Laravel rule name; the snake_case of the class name unless a subclass says otherwise. */
    public string $name {
        get => Str::snake(class_basename(static::class));
    }

    public ?string $messageKey {
        get => $this->name;
    }
}
