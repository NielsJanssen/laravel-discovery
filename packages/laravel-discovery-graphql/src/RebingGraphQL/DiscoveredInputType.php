<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Validation\Rule;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use Rebing\GraphQL\Support\InputType as RebingInputType;
use ReflectionProperty;
use RuntimeException;

/** A Rebing input object type built from a DiscoveredType; each field carries its own validation rules. */
final class DiscoveredInputType extends RebingInputType
{
    /** @var array{0: array<string, mixed>, 1: array<string, list<mixed>>}|null the provided rules of the last values, matched by value equality */
    private ?array $provided = null;

    public function __construct(
        private readonly Application $app,
        private readonly DiscoveredType $discoveredType,
    ) {}

    public function attributes(): array
    {
        $attributes = ['name' => $this->discoveredType->name];

        if ($this->discoveredType->description !== null) {
            $attributes['description'] = $this->discoveredType->description;
        }

        return $attributes;
    }

    public function fields(): array
    {
        $registry = $this->app->make(TypeRegistry::class);
        $fields = [];

        foreach ($this->discoveredType->fields as $field) {
            $definition = [
                'type' => $registry->resolve($field->type, Position::Input),
                'rules' => $this->rulesResolver($field),
            ];

            if ($field->description !== null) {
                $definition['description'] = $field->description;
            }

            if ($field->deprecationReason !== null) {
                $definition['deprecationReason'] = $field->deprecationReason;
            }

            if ($field->hasDefault && $field->defaultValue !== null) {
                $definition['defaultValue'] = $field->defaultValue;
            }

            if ($field->name !== $field->phpName) {
                $definition['alias'] = $field->phpName;
            }

            $fields[$field->name] = $definition;
        }

        return $fields;
    }

    /**
     * Rebing calls it with the input object's values and the request's args.
     *
     * @return Closure(array<string, mixed>, array<string, mixed>=): list<mixed>
     */
    private function rulesResolver(DiscoveredTypeField $field): Closure
    {
        return fn(array $values, array $request = []): array => $this->rules(
            $field,
            array_filter($values, is_string(...), ARRAY_FILTER_USE_KEY),
            array_filter($request, is_string(...), ARRAY_FILTER_USE_KEY),
        );
    }

    /**
     * @param  array<string, mixed>  $values  the input object's values, keyed by field name
     * @param  array<string, mixed>  $request
     * @return list<mixed>
     */
    private function rules(DiscoveredTypeField $field, array $values, array $request): array
    {
        $rules = [];

        if ($field->binding !== null && ! $field->binding->nullable) {
            $model = new $field->binding->modelClass();
            $rules[] = Rule::exists($model->getTable(), $model->getRouteKeyName());
        }

        if ($field->hasRules) {
            $declared = $this->declaredRules($field->phpName);
            $declared = $declared instanceof Closure ? $declared($values, $request) : $declared;

            $rules = [...$rules, ...match (true) {
                is_array($declared) => array_values($declared),
                is_string($declared) => explode('|', $declared),
                default => [$declared],
            }];
        }

        return [...$rules, ...$this->providedRules($values)[$field->phpName] ?? []];
    }

    /**
     * @param  array<string, mixed>  $values  keyed by field name
     * @return array<string, list<mixed>>  keyed by property name
     */
    private function providedRules(array $values): array
    {
        $properties = $this->discoveredType->toProperties($values);

        if ($this->provided !== null && $this->provided[0] === $properties) {
            return $this->provided[1];
        }

        $rules = [];

        foreach ($this->app->make(RuleProviderRegistry::class)->rulesForInput($this->discoveredType->class, $properties)->rules as $property => $contributed) {
            $rules[$property] = is_array($contributed) ? array_values($contributed) : [$contributed];
        }

        $this->provided = [$properties, $rules];

        return $rules;
    }

    /**
     * @return array<int|string, mixed>|Closure
     */
    private function declaredRules(string $property): array|Closure
    {
        $class = $this->discoveredType->class;
        $field = (new ReflectionProperty($class, $property)->getAttributes(Field::class)[0] ?? null)?->newInstance();

        return $field->rules ?? throw new RuntimeException("Could not find #[Field(rules:)] on $class::\$$property. Run discovery:clear after changing an #[Input] class.");
    }
}
