<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use ReflectionClass;

/** Loads models whose column matches a property of each root. Options: model, key, column (default: the model's key name), many. */
final readonly class KeyLoader implements BatchLoader, VerifiesLoadOptions
{
    private const array OPTIONS = ['model', 'key', 'column', 'many'];

    public function load(array $roots, array $options, array $args): array
    {
        $model = $options['model'] ?? null;
        $key = $options['key'] ?? null;

        if (! is_string($model) || ! is_a($model, Model::class, true) || ! is_string($key)) {
            throw new LogicException('KeyLoader needs the options model, an Eloquent model class, and key, a property of the root.');
        }

        $column = $options['column'] ?? new $model()->getKeyName();
        $many = ($options['many'] ?? false) === true;

        if (! is_string($column)) {
            throw new LogicException('KeyLoader needs the option column to be a column name.');
        }

        $values = array_map(static fn(object $root): mixed => $root->{$key}, $roots);
        $lookup = array_values(array_unique(array_filter($values, static fn(mixed $value): bool => is_int($value) || is_string($value))));

        $records = $lookup === [] ? new EloquentCollection() : $model::query()->whereIn($column, $lookup)->get();
        $grouped = [];

        foreach ($records as $record) {
            $value = $record->getAttribute($column);

            if (is_int($value) || is_string($value)) {
                $grouped[$value][] = $record;
            }
        }

        return array_map(static function (mixed $value) use ($grouped, $many): mixed {
            $matches = is_int($value) || is_string($value) ? $grouped[$value] ?? [] : [];

            return $many ? new EloquentCollection($matches) : $matches[0] ?? null;
        }, $values);
    }

    public static function verifyOptions(string $member, string $attribute, DiscoveredTypeField $field, array $options): void
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);

        if ($unknown !== []) {
            throw new LogicException(sprintf(
                '%s has %s with the unknown option %s. KeyLoader takes %s.',
                $member,
                $attribute,
                implode(', ', $unknown),
                implode(', ', self::OPTIONS),
            ));
        }

        $model = $options['model'] ?? null;

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new LogicException("$member has $attribute without model:, or with one that is not an Eloquent model. Name the model to load, as in model: Author::class.");
        }

        $key = $options['key'] ?? null;

        if (! is_string($key) || $key === '') {
            throw new LogicException("$member has $attribute without key:. Name the property of the parent that holds the value to match, as in key: 'authorId'.");
        }

        $class = $field->typeClass;

        if ($class !== null && ! is_a($class, Model::class, true) && ! self::hasPublicProperty($class, $key)) {
            throw new LogicException("$member has $attribute with key: '$key', but $class has no public property \$$key. Name a public property of $class.");
        }

        if (isset($options['column']) && ! is_string($options['column'])) {
            throw new LogicException("$member has $attribute with a column: that is not a string. Name the column to match.");
        }

        $many = $options['many'] ?? false;

        if (! is_bool($many)) {
            throw new LogicException("$member has $attribute with a many: that is not a boolean.");
        }

        if ($many !== $field->type->list) {
            throw new LogicException($many
                ? "$member has $attribute with many: true, which loads a list, but the field is a single value. Use #[Field(of: ...)]."
                : "$member has $attribute, which loads a single record, but the field is a list. Add many: true, or make the field a single value.");
        }

        $column = $options['column'] ?? null;
        $keyName = self::keyName($model);

        if (! $many && $column !== null && $column !== $keyName) {
            throw new LogicException(sprintf(
                "%s has %s with column: '%s' and loads a single record, but only the key column '%s' of %s is known to be unique. Add many: true to load every match, or key on the unique column '%s'.",
                $member,
                $attribute,
                $column,
                $keyName,
                $model,
                $keyName,
            ));
        }
    }

    /**
     * @param  class-string  $class
     */
    private static function hasPublicProperty(string $class, string $property): bool
    {
        $reflection = new ReflectionClass($class);

        return $reflection->hasProperty($property)
            && $reflection->getProperty($property)->isPublic()
            && ! $reflection->getProperty($property)->isStatic();
    }

    /**
     * The model's key name from its $primaryKey default, read without instantiating the model.
     *
     * @param  class-string<Model>  $model
     */
    private static function keyName(string $model): string
    {
        $keyName = new ReflectionClass($model)->getProperty('primaryKey')->getDefaultValue();

        return is_string($keyName) ? $keyName : 'id';
    }
}
