<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;

/** Eager loads a relation on the roots that have not loaded it yet, one query per root class. Option: relation. */
final readonly class RelationLoader implements BatchLoader, VerifiesLoadOptions
{
    public function load(array $roots, array $options, array $args): array
    {
        $relation = $options['relation'] ?? null;

        if (! is_string($relation)) {
            throw new LogicException('RelationLoader needs the option relation, the name of the relation method.');
        }

        $missing = [];

        foreach ($roots as $root) {
            if (! $root instanceof Model) {
                throw new LogicException(sprintf('RelationLoader loads %s on Eloquent models only, got %s.', $relation, get_debug_type($root)));
            }

            if (! $root->relationLoaded($relation)) {
                $missing[$root::class][] = $root;
            }
        }

        foreach ($missing as $models) {
            new EloquentCollection($models)->load($relation);
        }

        /** @var list<Model> $roots */
        return array_map(static fn(Model $root): mixed => $root->getRelation($relation), $roots);
    }

    public static function verifyOptions(string $member, string $attribute, DiscoveredTypeField $field, array $options): void
    {
        $relation = $options['relation'] ?? null;
        $class = $field->typeClass;

        if (! is_string($relation) || $relation === '') {
            throw new LogicException("$member has $attribute without a relation name. Name the relation method, as in #[Relation('author')].");
        }

        if ($class === null || ! is_a($class, Model::class, true)) {
            throw new LogicException(sprintf(
                '%s has %s, but %s is not an Eloquent model, so it has no relations to load. Use #[Load(KeyLoader::class, ...)] or your own BatchLoader instead.',
                $member,
                $attribute,
                $class ?? 'its type',
            ));
        }

        if (! method_exists($class, $relation)) {
            throw new LogicException("$member has $attribute for the relation '$relation', but $class has no method $relation(). Name an existing relation method.");
        }

        if ($field->type->class !== null && is_a($field->type->class, EloquentRelation::class, true)) {
            throw new LogicException(sprintf(
                '%s has %s, so its #[Field] needs type: for a single record or of: for a list, as in #[Field(of: Book::class)]. The type is not read from the relation.',
                $member,
                $attribute,
            ));
        }
    }
}
