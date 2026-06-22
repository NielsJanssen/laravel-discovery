<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Closure;
use NielsJanssen\Laravel\Validation\Rule\Accepted;
use NielsJanssen\Laravel\Validation\Rule\Email;
use NielsJanssen\Laravel\Validation\Rule\Max;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Rule;
use NielsJanssen\Laravel\Validation\Rule\Valid;

final class Profile
{
    #[Min(5), Max(255)]
    public string $name = 'Niels Janssen';

    #[Email]
    public ?string $email = 'niels@example.com';

    #[Rule(new EvenNumber())]
    #[Rule('min:18', message: 'Too young.')]
    public int $age = 30;

    /** A closure is a rule *factory*; Laravel's $fail callback is what it returns. */
    #[Rule(static function (): Closure {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value <= 0) {
                $fail('must be positive');
            }
        };
    })]
    public int $score = 5;

    #[Valid]
    public Address $address;

    /** Computed/virtual property — must be read through the get hook. */
    #[Accepted]
    public bool $hasEmail {
        get => $this->email !== null;
    }

    public function __construct()
    {
        $this->address = new Address('123 Main Street');
    }
}
