<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * Shared body for #[Unique] and #[Exists]: the table, the column and the query constraints Laravel's
 * DatabaseRule offers, expressed as typed constructor arguments.
 *
 *   #[Exists(User::class, 'id', where: ['tenant_id' => 3, 'deleted_reason' => null])]
 *   #[Exists('users', where: static function (Builder $query, ValidationContext $c): void { $query->where('tenant_id', $c->root->tenantId); })]
 *
 * An array `where` maps a column to a value; `null` means `whereNull` and a list means `whereIn`. A
 * closure receives the query builder and the ValidationContext, so it can read sibling values.
 */
abstract class DatabaseRule extends NamedRule
{
    /**
     * @param  class-string<Model>|string  $table
     * @param  array<string, string|int|bool|array<int, mixed>|\UnitEnum|null>|Closure(Builder, ValidationContext): void|null  $where
     */
    public function __construct(
        public readonly string $table,
        public readonly ?string $column = null,
        public readonly array|Closure|null $where = null,
        public readonly bool $withoutTrashed = false,
        public readonly bool $onlyTrashed = false,
        public readonly string $deletedAtColumn = 'deleted_at',
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Apply the shared constraints to a freshly built Laravel rule.
     *
     * @template T of Exists|Unique
     *
     * @param  T  $rule
     * @return T
     */
    protected function constrain(Exists|Unique $rule, ValidationContext $context): Exists|Unique
    {
        if ($this->where instanceof Closure) {
            $where = $this->where;

            $rule->using(static function (Builder $query) use ($where, $context): void {
                $where($query, $context);
            });
        } else {
            foreach ($this->where ?? [] as $column => $value) {
                $rule->where($column, $value);
            }
        }

        if ($this->withoutTrashed) {
            $rule->withoutTrashed($this->deletedAtColumn);
        }

        if ($this->onlyTrashed) {
            $rule->onlyTrashed($this->deletedAtColumn);
        }

        return $rule;
    }
}
