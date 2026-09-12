<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * Marks an attribute as a *type* rule (string, integer, numeric, boolean, array, list, enum). A
 * member has exactly one type, so an explicit type attribute replaces whatever TypeInferrer would
 * have added: `#[Numeric] public int $amount` yields `numeric`, not `integer` as well.
 */
interface TypeRule extends ValidationRule {}
