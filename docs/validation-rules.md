# Validation rules

Every Laravel rule has a matching attribute under `NielsJanssen\Laravel\Validation\Rule`. The rule string is the
snake_case of the class name, and the constructor is typed for that rule's own arguments, so a wrong one is a PHP error
rather than a validation surprise.

```php
#[Min(5)]                  // min:5
#[Between(1, 10)]          // between:1,10
#[In(['nl', 'be'])]        // in:"nl","be"
#[Digits(6)]               // digits:6
#[Email]                   // email
```

Every attribute takes `message:` last, and every one is repeatable, so several can stack on one member. Multi-value
arguments are arrays rather than variadics (`#[In(['nl', 'be'])]`, not `#[In('nl', 'be')]`), which is what leaves room
for that trailing `message:`.

For the meaning of each rule, see the
[Laravel validation documentation](https://laravel.com/docs/validation#available-validation-rules).

## Types

Inference adds these for you; write one out to override the inferred rule. The `Type` suffix dodges PHP's reserved words
and is not part of the rule string.

| Attribute | Rule |
|---|---|
| `StringType()` | `string` |
| `Integer(bool $strict = false)` | `integer` |
| `Numeric()` | `numeric` |
| `Boolean(bool $strict = false)` | `boolean` |
| `ArrayType(?array $keys = null)` | `array`, optionally limited to those keys |
| `ListType()` | `list` |
| `Enum(string $class, ?array $only = null, ?array $except = null)` | Laravel's `Enum` rule object |

## Presence

| Attribute | Rule |
|---|---|
| `Required()` `Present()` `Filled()` `Missing()` `Prohibited()` `Nullable()` `Sometimes()` `Bail()` `Exclude()` | the rule of the same name |
| `RequiredWith(array $fields)` `RequiredWithAll` `RequiredWithout` `RequiredWithoutAll` | `required_with:a,b` |
| `PresentWith(array $fields)` `PresentWithAll` `MissingWith` `MissingWithAll` `ExcludeWith` `ExcludeWithout` `Prohibits` | idem |
| `PresentIf(string $field, string\|int\|float\|bool\|array $values)` `PresentUnless` `MissingIf` `MissingUnless` | `present_if:plan,pro` |
| `RequiredIfAccepted(string $field)` `RequiredIfDeclined` `ProhibitedIfAccepted` `ProhibitedIfDeclined` | `required_if_accepted:terms` |
| `RequiredArrayKeys(array $keys)` | `required_array_keys:a,b` |

The condition-carrying `RequiredIf`, `RequiredUnless`, `ProhibitedIf`, `ProhibitedUnless`, `ExcludeIf`, `ExcludeUnless`,
`AcceptedIf` and `DeclinedIf` are covered under [conditional rules](validation.md#conditional-rules).

## Size and number

| Attribute | Rule |
|---|---|
| `Min(int\|float $value)` `Max` `Size` | `min:5` |
| `Between(int\|float $min, int\|float $max)` | `between:1,10` |
| `Digits(int $length)` | `digits:6` |
| `MinDigits(int $value)` `MaxDigits(int $value)` | `min_digits:2` |
| `DigitsBetween(int $min, int $max)` | `digits_between:2,4` |
| `Decimal(int $min, ?int $max = null)` | `decimal:2` |
| `MultipleOf(int\|float $value)` | `multiple_of:0.5` |

## Comparing with another field

| Attribute | Rule |
|---|---|
| `Same(string $field)` `Different(string $field)` | `same:other` |
| `Gt(string\|int\|float $fieldOrValue)` `Gte` `Lt` `Lte` | `gt:other` |
| `InArray(string $field)` | `in_array:other.*` |
| `InArrayKeys(array $keys)` | `in_array_keys:a,b` |
| `Confirmed(?string $field = null)` | `confirmed`, or `confirmed:repeat` |

## Strings

| Attribute | Rule |
|---|---|
| `Alpha(bool $ascii = false)` `AlphaDash` `AlphaNum` | `alpha`, or `alpha:ascii` |
| `Ascii()` `Lowercase()` `Uppercase()` `Json()` `Ulid()` `HexColor()` `MacAddress()` `ActiveUrl()` | the rule of the same name |
| `Ip()` `Ipv4()` `Ipv6()` | `ip`, `ipv4`, `ipv6` |
| `Uuid(int\|string\|null $version = null)` | `uuid`, or `uuid:4` |
| `Url(array $protocols = [])` | `url`, or `url:https` |
| `Email(bool $strict, bool $dns, bool $spoof, bool $native, bool $unicode)` | `email`, or Laravel's `Email` rule object once a flag is set |
| `Regex(string $pattern)` `NotRegex(string $pattern)` | `regex:/…/` |
| `StartsWith(array $values)` `DoesntStartWith` `EndsWith` `DoesntEndWith` | `starts_with:a,b` |
| `Encoding(string $encoding)` | `encoding:UTF-8` |
| `Timezone(string $group = 'all', ?string $country = null)` | `timezone:all` |
| `Password(int $min = 8, bool $letters, bool $mixedCase, bool $numbers, bool $symbols, ?int $uncompromised, ?int $max)` | Laravel's `Password` rule object |

## Sets and arrays

| Attribute | Rule |
|---|---|
| `In(array $values)` `NotIn(array $values)` | `in:"nl","be"`. The rule object quotes, so a value holding a comma survives. |
| `Contains(array $values)` `DoesntContain(array $values)` | `contains:"a","b"` |
| `Distinct(bool $strict = false, bool $ignoreCase = false)` | `distinct`, `distinct:strict` |
| `AnyOf(array $ruleSets)` | Laravel's `AnyOf` rule object |

## Dates

| Attribute | Rule |
|---|---|
| `Date()` | `date` |
| `DateFormat(array $formats)` | `date_format:Y-m-d,Y-m-d H:i` |
| `After(DateTimeInterface\|string $date)` `AfterOrEqual` `Before` `BeforeOrEqual` `DateEquals` | `after:today`, `after:2030-01-01 00:00:00`, or another field's name |

## Files

| Attribute | Rule |
|---|---|
| `File()` | `file` |
| `Image(bool $allowSvg = false)` | `image`, or `image:allow_svg` |
| `Mimes(array $values)` `Mimetypes` `Extensions` | `mimes:pdf,png` |
| `ImageFile(bool $allowSvg = false)` | Laravel's `File::image()` rule object |
| `Dimensions(?int $width, ?int $height, ?int $minWidth, ?int $minHeight, ?int $maxWidth, ?int $maxHeight, $ratio, $minRatio, $maxRatio, ?array $ratioBetween)` | `dimensions:min_width=200,ratio=3/2` |

## Database

```php
#[Unique(User::class, 'email', ignore: static function (ValidationContext $c) { return $c->root->id; })]
public string $email = '';

#[Exists('teams', where: ['archived_at' => null])]
public int $teamId = 0;
```

| Attribute | Signature |
|---|---|
| `Unique` | `(string $table, ?string $column = null, int\|string\|Closure\|null $ignore = null, ?string $ignoreColumn = null, array\|Closure\|null $where = null, bool $withoutTrashed = false, bool $onlyTrashed = false, string $deletedAtColumn = 'deleted_at')` |
| `Exists` | `(string $table, ?string $column = null, array\|Closure\|null $where = null, bool $withoutTrashed = false, bool $onlyTrashed = false, string $deletedAtColumn = 'deleted_at')` |

`$table` takes a model class as well as a table name. An array `$where` maps a column to a value, where `null` means "is
null" and a list means "is one of". A closure `$where` receives the query builder and the `ValidationContext`, and an
`ignore` closure receives the context and returns the model or its key, so the row being edited stays out of its own
uniqueness check.

## Authorization

| Attribute | Rule |
|---|---|
| `Can(string $ability, array $arguments = [])` | Laravel's `Can` rule object |

`#[Can]` authorizes the value under validation. It is not the tool for authorizing a model bound to a
`#[Query]`/`#[Mutation]` parameter, since validation sees the raw id rather than the record; use
[`#[Authorize]`](graphql-authorization.md#authorizing-a-bound-model) there.

## The generic `#[Rule]` attribute

`#[Rule]` takes anything Laravel's validator accepts, in four spellings.

```php
use Illuminate\Validation\Rule as LaravelRule;   // the facade, not our attribute
use NielsJanssen\Laravel\Validation\Rule\Rule;
use NielsJanssen\Laravel\Validation\ValidationContext;

final class Subscription
{
    #[Rule('timezone:Europe')]                       // a rule string
    public string $timezone = 'Europe/Amsterdam';

    #[Rule(['min:10', 'max:20'])]                    // several at once
    public string $slug = '';

    #[Rule(new ActiveMandate())]                     // a ValidationRule object
    public string $mandateId = '';

    #[Rule(static function (ValidationContext $context): object {
        return LaravelRule::in(Plan::available());    // a factory, run per validation
    })]
    public string $plan = '';
}
```

A closure is always a factory: it is called with the `ValidationContext` and whatever it returns becomes the rule (or,
for an array, the rules). That is what makes rules built at runtime possible, such as `Rule::exists(...)`,
`Rule::in(...)`, or a value read from config.

Laravel's own `function ($attribute, $value, $fail)` callback still works; return it from the factory.

```php
#[Rule(static function (): Closure {
    return static function (string $attribute, mixed $value, Closure $fail): void {
        if ($value < 1) {
            $fail('The :attribute must be a positive amount in euros.');
        }
    };
})]
public int $monthlyAmount = 0;
```

Attribute arguments must be constant expressions, so the closure must be a `static function` literal. An arrow function
(`fn`) captures the surrounding scope and does not qualify.

| Parameter | Type | Description |
|---|---|---|
| `rule` | `string\|array\|Closure\|object` | The rule, rules, rule object, or factory. |
| `message` | `?string` | An optional custom message for this rule. |

## `ValidationContext`

Every `rules()` call and every factory closure receives a `ValidationContext`.

| Property | Meaning |
|---|---|
| `root` | the object being validated (`null` when validating raw method arguments) |
| `path` | the dotted path this rule is being built for, such as `address.postalCode` |
| `value` | the current value at that path |

## Where to next

- [Validation](validation.md): entry points, type inference, nesting, messages, and custom attributes.
