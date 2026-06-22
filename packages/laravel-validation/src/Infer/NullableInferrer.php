<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Infer;

use NielsJanssen\Laravel\Validation\Rule\Nullable;
use NielsJanssen\Laravel\Validation\RuleCollection;
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\PropertyReflector;

/**
 * A nullable *type* means the value may be null. Whether the key may be absent is a separate
 * question, answered by SometimesInferrer.
 */
class NullableInferrer implements RuleInferrer
{
    public function __invoke(RuleCollection $rules, PropertyReflector|ParameterReflector $member): void
    {
        if ($member->getType()->isNullable()) {
            $rules->prepend(new Nullable());
        }
    }
}
