# Validation

`nielsjanssen/laravel-validation` declares validation rules on a class's properties or a method's parameters, so a data
object carries its own validation contract.

```php
use Illuminate\Support\Facades\Validator;
use NielsJanssen\Laravel\Validation\Rule\{Email, Max, Min};

final class Registration
{
    #[Min(2), Max(255)]
    public string $name = '';

    #[Email]
    public ?string $email = null;
}

$registration = new Registration();
$registration->name = 'Anouk de Vries';
$registration->email = 'anouk@example.nl';

Validator::validateObject($registration);
```

## Requirements

- PHP 8.5+
- Laravel 13+ (`illuminate/validation` ^13.15)

## Installation

```bash
composer require nielsjanssen/laravel-validation
```

The service provider registers itself through package discovery. It replaces the bound `validator` factory with a
subclass that adds the object and method entry points, keeping the original factory's behaviour intact (including the
presence verifier behind the `exists` and `unique` rules). There is nothing to publish.

The package works on its own. Installing `nielsjanssen/laravel-discovery` alongside it pre-builds and caches the rule
plans; see [Discovery and caching](#discovery-and-caching).

## Entry points

Four methods are added to the `Validator` facade.

| Method                                                                 | Returns                                                             |
|------------------------------------------------------------------------|---------------------------------------------------------------------|
| `makeFromObject($object, $rules = [], $messages = [])`                  | a standard `Illuminate\Validation\Validator`                        |
| `validateObject($object, $rules = [], $messages = [])`                  | the validated data, throwing `ValidationException` on failure       |
| `makeFromMethod($target, $method, $arguments, $rules = [], $messages = [])` | a validator built from the method's parameter attributes        |
| `validateMethod($target, $method, $arguments, $rules = [], $messages = [])` | the validated arguments                                         |

All four accept optional `$rules` and `$messages` that are merged over the derived ones, so a caller key takes
precedence for the same path.

```php
use NielsJanssen\Laravel\Validation\Rule\{Between, In, Min};

final class OrderLine
{
    #[Min(1)]
    public string $sku = '';

    #[Between(1, 999)]
    public int $quantity = 1;

    #[In(['pending', 'paid', 'shipped'])]
    public string $status = 'pending';
}

$validator = Validator::makeFromObject($orderLine);

if ($validator->fails()) {
    // $validator->errors() holds the messages, keyed by property name.
}
```

Because `makeFromObject()` returns a standard validator, everything you already use is available: `validate()`,
`validated()`, `passes()`, `fails()`, `errors()`.

### Method parameters

The same attributes work on parameters, which suits action classes and command handlers.

```php
final class PlaceOrder
{
    public function handle(
        #[Min(1)] string $sku,
        #[Between(1, 999)] int $quantity,
        ?string $note = null,          // no attribute, so not validated
    ): void {
        // ...
    }
}

Validator::validateMethod($action, 'handle', ['sku' => 'A1', 'quantity' => 5]);
```

This is also what drives argument validation in
[`nielsjanssen/laravel-discovery-graphql`](graphql-arguments.md), where `#[Query]` and `#[Mutation]` parameters are
validated through a hook that package exposes.

## Opt-in

Validation is opt-in. A property or parameter takes part only when it carries one of this package's attributes,
including type inference, so an object can hold internal state that never reaches the validator.

Properties of any visibility are read, so value objects built from promoted `readonly` constructor properties work like
public ones, as do hooked (virtual) properties, which are read through their `get` hook. Static properties and variadic
parameters are skipped.

## Type inference

Every opted-in member gets a base rule derived from its declared type, before its attributes are applied.

| Declared type      | Inferred rule                                |
|--------------------|----------------------------------------------|
| `string`           | `string`                                     |
| `int`              | `integer`                                    |
| `float`            | `numeric`                                    |
| `bool`             | `boolean`                                    |
| `array`, `iterable`| `array`                                      |
| a backed enum      | `Illuminate\Validation\Rules\Enum` for it    |

On top of that, a nullable type (`?string`) adds `nullable`, and a parameter with a default value adds `sometimes`,
because the caller may leave it out. A default does not add `nullable`: a defaulted `string $name` still must not be
null when given. A non-nullable type does not add `required`; add `#[Required]` when you want that. Union and
intersection types infer nothing, since no single rule fits.

So `#[Email] public ?string $email` produces `['nullable', 'string', 'email']`, and `#[Min(2)] public string $name`
produces `['string', 'min:2']`.

Writing an inferred rule out by hand is not a mistake. An explicit attribute replaces the inferred rule of the same
kind, so `#[Nullable, Min(2)] public ?string $x` yields `['string', 'nullable', 'min:2']` rather than `nullable` twice.
That is also how you override one: `#[Numeric] public int $amount` gives `numeric` instead of `integer`.

## Rules

Every Laravel rule has a matching attribute under `NielsJanssen\Laravel\Validation\Rule`, and anything missing is one
`#[Rule('...')]` away. See [Validation rules](validation-rules.md) for the full reference.

```php
use NielsJanssen\Laravel\Validation\Rule\{Alpha, Max, Min};

final class Employee
{
    #[Alpha, Min(2), Max(60)]
    public string $firstName = '';
}
```

## Custom error messages

Every named attribute takes `message:` as its last argument, keyed to its own rule, so it overrides that failure and
leaves the rest alone.

```php
#[Min(5, message: 'Give it at least five.')]
public string $name = '';
```

That produces `min:5` and keys the message at `name.min`. Stacked attributes each carry their own:

```php
#[Min(2, message: 'Two at least.'), Max(4, message: 'Four at most.')]
public string $code = '';
```

`#[Rule]` takes a `message:` too, for rules that have no attribute of their own. A message belongs to one rule, so
`#[Rule]` accepts one alongside a single rule only; pass a list and it throws.

The attributes backed by a Laravel rule object rather than a rule string (`Enum`, `Email` with a flag set, `Password`,
`Can`, `AnyOf`, `ImageFile`) are keyed by Laravel under the rule class name
(`attr.Illuminate\Validation\Rules\Password`) instead of a rule suffix. The attribute handles that; it only matters if
you write the key by hand in a lang file.

All four entry points take a `$messages` array that is merged over the attribute-derived ones, and Laravel's ordinary
`lang/validation.php` (including the `attributes` key for `:attribute` replacements) applies underneath, unchanged.

```php
Validator::makeFromObject($profile, [], ['name.min' => 'Far too short.']);
```

For a rule object or a factory the message is keyed on the field path rather than a rule suffix, since there is no rule
name to key to; those usually supply their own text through `$fail` anyway.

## Conditional rules

`#[RequiredIf]`, `#[RequiredUnless]`, `#[ProhibitedIf]`, `#[ProhibitedUnless]`, `#[ExcludeIf]`, `#[ExcludeUnless]`,
`#[AcceptedIf]` and `#[DeclinedIf]` accept a closure as well as the classic field-and-value form. The closure receives
the `ValidationContext`, so a rule can depend on sibling values through `$context->root`.

```php
use NielsJanssen\Laravel\Validation\Rule\{ProhibitedIf, RequiredIf};
use NielsJanssen\Laravel\Validation\ValidationContext;

final class OrderRequest
{
    public bool $isGift = false;

    #[RequiredIf(static function (ValidationContext $context): bool {
        return $context->root->isGift;
    })]
    public ?string $giftMessage = null;

    #[ProhibitedIf(static function (ValidationContext $context): bool {
        return ! $context->root->isGift;
    })]
    public ?string $giftWrapColour = null;
}
```

The field-and-value form compares against another field by name and produces the plain string rule; pass an array to
match any of several values.

```php
#[RequiredUnless('paymentMethod', 'invoice')]           // required_unless:paymentMethod,invoice
public ?string $iban = null;

#[RequiredIf('plan', ['pro', 'team'])]                  // required_if:plan,pro,team
public ?string $vatNumber = null;
```

Six of these are backed by Laravel's own condition-aware rule objects. `#[AcceptedIf]` and `#[DeclinedIf]` have no
native closure equivalent, so the package resolves them to `accepted` or `declined` when the condition holds and to
nothing when it does not.

The `missing` and `present` families are not offered as closures: they test whether a key is absent from the input, and
the validator always includes every annotated member, so the answer would never be meaningful.

## Nested objects

Mark a class-typed property with `#[Valid]` to validate the object it holds. The package recurses and prefixes the
property names, producing dotted keys such as `address.postalCode`. Nesting is arbitrarily deep, and each nested class
opts its own members in with their own attributes.

```php
use NielsJanssen\Laravel\Validation\Rule\{Min, Size, Valid};

final class Address
{
    #[Min(2)]
    public string $city = '';

    #[Size(6)]
    public string $postalCode = '';
}

final class DeliveryRequest
{
    #[Valid]
    public Address $address;
}
```

Without `#[Valid]`, a class-typed property is left untouched. `#[Valid]` combines with other attributes on the same
property, so `#[Required, Valid]` both requires the value and validates inside it.

`#[Valid]` needs no argument, since the declared type already says what is allowed and PHP enforces it. Passing classes
narrows an abstract or interface type to specific implementations.

```php
#[Valid]                              // allowed: Address and its subclasses
public Address $address;

#[Valid(Ltd::class, Plc::class)]      // a sole trader is rejected
public Payable $company;
```

## Iterables

A member holding many things gets one of two attributes, each validating every element at its own path. They do
different jobs and compose freely.

### `#[ListOf]`: the elements are objects

PHP carries no element type for an array, an `iterable`, or a `Collection`, so `#[ListOf]` requires at least one class,
and every element must be an instance of one of them.

```php
use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Validation\Rule\ListOf;

final class Basket
{
    #[ListOf(OrderLine::class)]
    public array $lines = [];

    #[ListOf(Person::class, Company::class)]     // either is accepted
    public Collection $customers;
}
```

That produces `lines.0.sku`, `lines.1.sku`, one rule set per element, so an error names the offending one. Subclasses of
an allowed class are accepted, string keys are preserved (`byRegion.north.code`), and an empty iterable contributes
nothing.

Anything the member is not declared to hold fails at its own path, rather than being validated against whatever class it
happens to be:

```php
$basket->customers = new Collection([new Person(), new Country('NL')]);

$validator->errors()->first('customers.1');
// The customers.1 must be Person or Company, Country given.
```

That is the point of naming them. Without it, dropping a `Country` into a `Collection $customers` would validate it as a
`Country` and pass, letting the contents define their own contract. Non-object elements fail the same way.

A `@var X[]` docblock is deliberately not consulted. It cannot be resolved reliably against the file's imports, and a
constraint that silently works for `@var \App\Country[]` but not for `@var Country[]` is worse than no constraint.

### `#[Each]`: rules on every element

`#[Each]` takes rules rather than classes, which is what an iterable of scalars needs.

```php
use NielsJanssen\Laravel\Validation\Rule\Each;

#[Each('email')]
public array $recipients = [];

#[Each('integer', 'between:1,10')]
public array $scores = [];
```

An element rule's message is keyed per element, matching how the rules themselves are built:

```php
#[Each(new Rule('email', message: 'That is not an email.'))]
public array $recipients = [];
```

```php
$recipients = ['a@example.com', 'nope'];

$validator->errors()->keys();                       // ['recipients.1']
$validator->errors()->first('recipients.1');        // 'That is not an email.'
```

### Composing them

The two are independent, so a list of objects can also carry rules on the elements themselves.

```php
#[ListOf(OrderLine::class)]
#[Each('required')]
public array $lines = [];
```

`lines.0` gets `required` from `#[Each]`, and `lines.0.sku` its rules from `OrderLine`.

### Picking the right one

`#[Valid]` is about the one object a member holds; `#[ListOf]` and `#[Each]` are about its elements. Mixing them up is
reported when the plan is built, rather than quietly doing nothing.

| You wrote                            | What happens                                                  |
|--------------------------------------|---------------------------------------------------------------|
| `#[ListOf]` with no arguments        | `ArgumentCountError` at the attribute itself                  |
| `#[Valid]` on an array or collection | `LogicException`: use `#[ListOf(Thing::class)]`               |
| `#[ListOf]` or `#[Each]` on a single object | `LogicException`: use `#[Valid]`                       |
| `#[Valid]` alongside either of them  | `LogicException`: a member holds one object or many           |
| `#[ListOf('App\Contry')]`            | `LogicException`: no such class or interface                  |
| `#[Each(Country::class)]`            | `LogicException`: that is a class, use `#[ListOf]`            |

The last two exist because `Country::class` and `'email'` are both plain strings. Without them, a class name that fails
to load, or a class handed to the wrong attribute, would reach the validator as a rule name and produce a baffling error
somewhere else entirely.

### The per-element trade-off

Rules are built per element rather than as a single `lines.*` wildcard, which is what lets a conditional or factory
closure inside `OrderLine` see its own element as `$context->root`. The cost is that a list of 500 elements produces 500
rule sets; the container itself still gets its own rules (`array`, plus anything else you put on it), so cap the size
there.

One consequence: `#[Each]` element rules are stored in the plan directly rather than re-read through reflection, so
unlike a property's own rules they cannot hold a closure. Put a factory on the element's own property with `#[Rule]`, or
pass a rule object.

## Custom rule attributes

Attributes are found by interface, so your own work with no registration. There are three ways in.

For a rule that follows the `name:arguments` string convention, extend `StringRule`. The rule string comes from the
class name, just like the built-in attributes, which pairs well with a rule registered through `Validator::extend()`.

```php
use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Iban extends StringRule {}   // produces the rule string "iban"
```

A rule that takes arguments declares them on a typed constructor and returns them from `parameters()`, in the order the
rule expects. Take `?string $message = null` last and hand it to the parent, and the attribute supports a custom message
like every built-in one.

```php
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class DivisibleBy extends StringRule
{
    public function __construct(
        public readonly int $divisor,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return [$this->divisor];   // #[DivisibleBy(3)] produces "divisible_by:3"
    }
}
```

Trailing nulls are dropped, so an optional argument that was not given leaves the rule string shorter rather than
emitting an empty parameter. Booleans, backed enums and `DateTimeInterface` values are formatted the way Laravel reads
them.

For a rule Laravel expresses as an object rather than a string, extend `NamedRule` and build the object in `rules()`,
never in the constructor, since the attribute has to stay serializable for the discovery cache.

```php
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ActiveMandate extends NamedRule
{
    public function rules(ValidationContext $context): array
    {
        return [new MandateRule($context->root->customerId)];
    }
}
```

For full control, implement `ValidationRule` and return whatever rules you like. This is how a project-specific concept
gets its own attribute.

```php
use NielsJanssen\Laravel\Validation\ValidationContext;
use NielsJanssen\Laravel\Validation\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Captcha implements ValidationRule
{
    public function rules(ValidationContext $context): array
    {
        return ['string', 'size:6', 'alpha_num'];
    }
}
```

`rules()` receives the context, so an attribute can depend on the object being validated or on the path it sits at;
there is no separate interface for that case.

Two optional interfaces give your attribute the behaviour the built-in ones have. Both are found by interface, so
nothing inside the package needs editing.

| Implement               | To get                                                                                                                                                       |
|-------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `MessageValidationRule` | your own error message, the way `#[Rule(message:)]` does: a `$message` and a `$messageKey` (the rule suffix, or `null` to key on the field path)              |
| `NestedValidationRule`  | control over nesting, the way `#[Valid]`, `#[ListOf]` and `#[Each]` do: a `$nesting` (`Nesting::Value` or `Nesting::Each`), the `$allowedClasses` elements may be, and `$elementRules` to apply to each |

```php
use NielsJanssen\Laravel\Validation\{MessageValidationRule, ValidationContext};

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Postcode implements MessageValidationRule
{
    public ?string $message { get => 'That is not a valid postcode.'; }

    public ?string $messageKey { get => 'regex'; }   // null keys on the field path instead

    public function rules(ValidationContext $context): array
    {
        return ['regex:/^[0-9]{4}\s?[A-Z]{2}$/'];
    }
}
```

Extending `Rule\NestingRule` saves writing the parts of `NestedValidationRule` you do not use; implementing the
interface directly works the same, since that is what the finder looks for. Several nesting attributes may sit on one
member as long as they agree on `$nesting`, which is why `#[ListOf]` and `#[Each]` compose, and why `#[Valid]` cannot
join them.

## Discovery and caching

Building rules means reflecting over a class, which is work you do not want to repeat on every request. With
[`nielsjanssen/laravel-discovery`](installation.md) installed, `RuleDiscovery` scans your application once and
pre-builds a plan for every class and method that uses validation attributes.

```bash
php artisan discovery:cache      # also runs as part of `php artisan optimize`
```

At validation time the plan is read straight from the cache. Nothing is required of you: a class that was never
discovered is reflected on demand, so the cache is only ever an optimisation.

One consequence is worth knowing. The cache is written with `serialize()`, and closures are not serializable, so an
attribute holding a closure (a `#[Rule]` factory, or a conditional's condition) is stored as a reference to where it
lives and re-read through reflection the first time it is used. That is transparent in normal use, but it ties the cache
to your source, so clear it after changing an attribute list.

```bash
php artisan discovery:clear      # also runs as part of `php artisan optimize:clear`
```

See [Installation](installation.md) for the environments the discovery cache applies to.

## Where to next

- [Validation rules](validation-rules.md): every attribute, the generic `#[Rule]`, and `ValidationContext`.
- [GraphQL argument validation](graphql-arguments.md): these attributes applied to `#[Query]` and `#[Mutation]`
  parameters.
