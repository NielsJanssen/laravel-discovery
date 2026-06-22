<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Infer;

use NielsJanssen\Laravel\Validation\RuleCollection;
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\PropertyReflector;

interface RuleInferrer
{
    public function __invoke(RuleCollection $rules, PropertyReflector|ParameterReflector $member): void;
}
