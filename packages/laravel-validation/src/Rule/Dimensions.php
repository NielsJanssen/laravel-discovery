<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Dimensions as LaravelDimensions;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 *   #[Dimensions(minWidth: 200, minHeight: 200, ratio: '3/2')]
 *
 * A ratio is a fraction ('3/2') or a float (1.5).
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Dimensions extends NamedRule
{
    /**
     * @param  array{0: int|float|string, 1: int|float|string}|null  $ratioBetween
     */
    public function __construct(
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?int $minWidth = null,
        public readonly ?int $minHeight = null,
        public readonly ?int $maxWidth = null,
        public readonly ?int $maxHeight = null,
        public readonly int|float|string|null $ratio = null,
        public readonly int|float|string|null $minRatio = null,
        public readonly int|float|string|null $maxRatio = null,
        public readonly ?array $ratioBetween = null,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public function rules(ValidationContext $context): array
    {
        // The constraints go in as an array: Dimensions types its fluent setters as int/float, while a
        // ratio is written as the fraction Laravel documents ('3/2').
        $constraints = array_filter([
            'width' => $this->width,
            'height' => $this->height,
            'min_width' => $this->minWidth,
            'min_height' => $this->minHeight,
            'max_width' => $this->maxWidth,
            'max_height' => $this->maxHeight,
            'ratio' => $this->ratio,
            'min_ratio' => $this->minRatio,
            'max_ratio' => $this->maxRatio,
        ], static fn(mixed $value): bool => $value !== null);

        if ($this->ratioBetween !== null) {
            $constraints['min_ratio'] = $this->ratioBetween[0];
            $constraints['max_ratio'] = $this->ratioBetween[1];
        }

        return [new LaravelDimensions($constraints)];
    }
}
