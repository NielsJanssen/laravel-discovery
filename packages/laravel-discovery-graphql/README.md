> [!NOTE]
> This is a **read-only split mirror** of the [laravel-discovery monorepo](https://github.com/NielsJanssen/laravel-discovery).
> Please open issues and pull requests there.

# laravel-discovery-graphql

Register [Rebing GraphQL](https://github.com/rebing/graphql-laravel) queries and mutations from attributes. A method
carrying `#[Query]` or `#[Mutation]` becomes a field in your schema, with its arguments taken from the method signature.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class Inventory
{
    #[Query(type: 'Product')]
    public function product(#[Arg('id')] #[Authorize('view')] Product $product): Product
    {
        return $product;
    }
}
```

The `id` argument resolves to the model, the `view` policy runs before the resolver, and the field appears in your
schema as `product(id: ID!): Product!`.

## Requirements

- PHP 8.5+
- Laravel 13+
- `rebing/graphql-laravel` ^10.0
- `nielsjanssen/laravel-discovery` ^1.0

## Installation

```bash
composer require nielsjanssen/laravel-discovery-graphql
```

The service provider registers itself through Laravel's package discovery.

## Documentation

- [GraphQL](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/graphql.md): queries, mutations,
  arguments, model binding, schemas, pagination, and sorting.
- [GraphQL authorization](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/graphql-authorization.md):
  `#[Authorize]`, custom gates, and authorizing a bound model.
- [GraphQL argument validation and hydration](https://github.com/NielsJanssen/laravel-discovery/blob/main/docs/graphql-arguments.md):
  validating arguments and hydrating value objects, and the hooks for wiring in your own library.
- [Full documentation](https://github.com/NielsJanssen/laravel-discovery#documentation) for the rest of the monorepo.
