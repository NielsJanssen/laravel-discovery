<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\AnyOf;
use NielsJanssen\Laravel\Validation\Rule\Can;

final class AuthorizationRules
{
    #[Can('update-post')]
    public mixed $post = null;

    #[Can('transfer', ['team'])]
    public mixed $withArguments = null;

    #[AnyOf([['string', 'min:3'], ['integer']])]
    public mixed $anyOf = null;
}
