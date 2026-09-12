> [!NOTE]
> This is a **read-only split mirror** of the [laravel-discovery monorepo](https://github.com/NielsJanssen/laravel-discovery).
> Please open issues and pull requests there.

# laravel-validation

Attribute-based validation for Laravel. You declare validation rules directly on a class's properties or on a method's
parameters, then validate in one call. The rules live next to the data they describe, so a data object carries its own
validation contract.

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
subclass that adds the object and method entry points, keeping the original factory's behaviour intact. There is
nothing to publish.

## Documentation

- [Validation](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/validation.md): entry points, type
  inference, messages, conditional rules, nested objects, iterables, and custom rule attributes.
- [Validation rules](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/validation-rules.md): every
  attribute, the generic `#[Rule]`, and `ValidationContext`.
- [GraphQL argument validation](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/graphql-arguments.md):
  these attributes applied to `#[Query]` and `#[Mutation]` parameters.
- [Full documentation](https://github.com/NielsJanssen/laravel-discovery#documentation) for the rest of the monorepo.
