<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/** A rule that keeps the data and validator Laravel hands it, and compares against the confirmation it finds there. */
final class MatchesConfirmation implements DataAwareRule, ValidationRule, ValidatorAwareRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    private ?Validator $validator = null;

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $confirmation = Arr::get($this->data, Str::replaceLast('password', 'confirmation', $attribute));

        if ($this->validator?->getData() !== $this->data || $value !== $confirmation) {
            $fail('The :attribute does not match ' . (is_string($confirmation) ? $confirmation : 'nothing') . '.');
        }
    }
}
