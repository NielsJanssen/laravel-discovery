<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Infer;

use NielsJanssen\Laravel\Validation\Rule\Sometimes;
use NielsJanssen\Laravel\Validation\RuleCollection;
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\PropertyReflector;

/**
 * A parameter with a default value may simply be left out by the caller, so its key can be
 * missing from the data. That is `sometimes`, not `nullable` — a defaulted `string $name` still
 * must not be null when it *is* given.
 */
class SometimesInferrer implements RuleInferrer
{
    public function __invoke(RuleCollection $rules, PropertyReflector|ParameterReflector $member): void
    {
        if ($member instanceof ParameterReflector && ! $member->isRequired()) {
            $rules->prepend(new Sometimes());
        }
    }
}
