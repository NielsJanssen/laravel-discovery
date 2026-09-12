<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Exists as LaravelExists;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * A row must hold this value.
 *
 *   #[Exists(Team::class, 'id')]
 *   #[Exists('teams', where: ['archived_at' => null])]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Exists extends DatabaseRule
{
    public function rules(ValidationContext $context): array
    {
        return [$this->constrain(new LaravelExists($this->table, $this->column ?? 'NULL'), $context)];
    }
}
