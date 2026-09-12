<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique as LaravelUnique;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * No other row may hold this value.
 *
 *   #[Unique(User::class, 'email')]
 *   #[Unique(User::class, 'email', ignore: static function (ValidationContext $c) { return $c->root->id; })]
 *
 * The `ignore` closure keeps the row being edited out of the check; return the model or its key.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Unique extends DatabaseRule
{
    /**
     * @param  class-string<Model>|string  $table
     * @param  int|string|Closure(ValidationContext): (Model|int|string|null)|null  $ignore
     * @param  array<string, string|int|bool|array<int, mixed>|\UnitEnum|null>|Closure(Builder, ValidationContext): void|null  $where
     */
    public function __construct(
        string $table,
        ?string $column = null,
        public readonly int|string|Closure|null $ignore = null,
        public readonly ?string $ignoreColumn = null,
        array|Closure|null $where = null,
        bool $withoutTrashed = false,
        bool $onlyTrashed = false,
        string $deletedAtColumn = 'deleted_at',
        ?string $message = null,
    ) {
        parent::__construct($table, $column, $where, $withoutTrashed, $onlyTrashed, $deletedAtColumn, $message);
    }

    public function rules(ValidationContext $context): array
    {
        $rule = new LaravelUnique($this->table, $this->column ?? 'NULL');

        $ignore = $this->ignore instanceof Closure
            ? ($this->ignore)($context)
            : $this->ignore;

        if ($ignore !== null) {
            $rule->ignore($ignore, $this->ignoreColumn);
        }

        return [$this->constrain($rule, $context)];
    }
}
