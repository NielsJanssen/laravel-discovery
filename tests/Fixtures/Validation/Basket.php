<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Validation\Rule\Each;
use NielsJanssen\Laravel\Validation\Rule\ListOf;
use NielsJanssen\Laravel\Validation\Rule\Min;

/** Iterables: #[ListOf] names the element classes, #[Each] the rules every element must pass. */
final class Basket
{
    #[Min(3)]
    public string $reference = 'BSK-1';

    #[ListOf(Country::class)]
    public array $shipTo = [];

    #[ListOf(Customer::class)]
    public Collection $customers;

    #[ListOf(Country::class)]
    public array $byRegion = [];

    #[ListOf(ConditionalForm::class)]
    public array $forms = [];

    /** Two classes are allowed here. */
    #[ListOf(Country::class, Customer::class)]
    public array $mixed = [];

    /** Scalar elements: the rules apply to each one. */
    #[Each('email')]
    public array $recipients = [];

    #[Each('integer', 'between:1,10')]
    public array $scores = [];

    /** #[ListOf] and #[Each] compose: every element is a Country AND passes the rule. */
    #[ListOf(Country::class)]
    #[Each('required')]
    public array $composed = [];

    /** Not marked: left completely alone. */
    public array $untouched = [];

    public function __construct()
    {
        $this->customers = new Collection();
    }
}
