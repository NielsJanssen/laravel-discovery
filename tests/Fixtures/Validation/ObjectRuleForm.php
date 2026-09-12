<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\AnyOf;
use NielsJanssen\Laravel\Validation\Rule\Can;
use NielsJanssen\Laravel\Validation\Rule\Email;
use NielsJanssen\Laravel\Validation\Rule\Enum;
use NielsJanssen\Laravel\Validation\Rule\ImageFile;
use NielsJanssen\Laravel\Validation\Rule\In;
use NielsJanssen\Laravel\Validation\Rule\Password;

/** The attributes that resolve to one of Laravel's own rule objects. */
final class ObjectRuleForm
{
    #[Enum(Status::class, only: [Status::Draft, Status::Open], message: 'Not a status we accept.')]
    public mixed $narrowed = 'draft';

    #[Enum(Status::class, except: [Status::Closed])]
    public mixed $excepted = 'draft';

    #[Email]
    public mixed $bareEmail = 'ada@example.com';

    #[Email(strict: true)]
    public mixed $strictEmail = 'ada@example.com';

    #[Password(min: 12, letters: true, numbers: true, message: 'Pick a stronger password.')]
    public mixed $password = 'sup3rsecretphrase';

    #[Can('update-post')]
    public mixed $post = null;

    #[AnyOf([['string', 'min:3'], ['integer']])]
    public mixed $anyOf = 'abcd';

    #[ImageFile]
    public mixed $avatar = null;

    #[ImageFile(allowSvg: true)]
    public mixed $vector = null;

    #[In(['nl', 'be'], message: 'Pick a country we ship to.')]
    public mixed $country = 'nl';
}
