<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\File as LaravelFile;
use Illuminate\Validation\Rules\ImageFile as LaravelImageFile;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * Laravel's File::image() rule object. Use #[Image] for the plain `image` rule string.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ImageFile extends NamedRule
{
    public function __construct(
        public readonly bool $allowSvg = false,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public ?string $messageKey {
        get => LaravelImageFile::class;
    }

    public function rules(ValidationContext $context): array
    {
        return [LaravelFile::image($this->allowSvg)];
    }
}
