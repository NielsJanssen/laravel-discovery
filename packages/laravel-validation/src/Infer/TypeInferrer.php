<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Infer;

use BackedEnum;
use NielsJanssen\Laravel\Validation\Rule\ArrayType;
use NielsJanssen\Laravel\Validation\Rule\BooleanType;
use NielsJanssen\Laravel\Validation\Rule\Enum;
use NielsJanssen\Laravel\Validation\Rule\IntegerType;
use NielsJanssen\Laravel\Validation\Rule\NumericType;
use NielsJanssen\Laravel\Validation\Rule\StringType;
use NielsJanssen\Laravel\Validation\RuleCollection;
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\PropertyReflector;

/**
 * Adds the base rule implied by the member's declared type, so the rules stay aligned with
 * the type you already wrote. Union and intersection types are left alone.
 */
class TypeInferrer implements RuleInferrer
{
    public function __invoke(RuleCollection $rules, PropertyReflector|ParameterReflector $member): void
    {
        $type = $member->getType();

        if ($type->isUnion() || $type->isIntersection()) {
            return;
        }

        // getName() keeps the leading `?` of a nullable type; NullableInferrer handles that half.
        $name = ltrim($type->getName(), '?');

        if (is_a($name, BackedEnum::class, allow_string: true)) {
            $rules->push(new Enum($name));

            return;
        }

        $rule = match ($name) {
            'string' => new StringType(),
            'int' => new IntegerType(),
            'float' => new NumericType(),
            'bool' => new BooleanType(),
            'array', 'iterable' => new ArrayType(),
            default => null,
        };

        if ($rule !== null) {
            $rules->push($rule);
        }
    }
}
