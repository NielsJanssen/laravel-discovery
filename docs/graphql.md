# GraphQL

`nielsjanssen/laravel-discovery-graphql` registers [Rebing GraphQL](https://github.com/rebing/graphql-laravel) queries
and mutations from attributes. A method carrying `#[Query]` or `#[Mutation]` becomes a field in your schema.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class Inventory
{
    #[Query(type: 'Product', list: true)]
    public function products(#[Arg] ?string $warehouse = null): array
    {
        return Product::query()->when($warehouse, fn ($q) => $q->where('warehouse', $warehouse))->get()->all();
    }
}
```

The field appears in `config('graphql.schemas')` and in the schema as `products(warehouse: String): [Product!]!`.

## Requirements

- PHP 8.5+
- Laravel 13+
- `rebing/graphql-laravel` ^10.0
- `nielsjanssen/laravel-discovery` ^1.0

## Installation

```bash
composer require nielsjanssen/laravel-discovery-graphql
```

The service provider (`NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider`) registers itself
through Laravel's package discovery. Configure Rebing GraphQL as you normally would; this package writes into the
schema configuration rather than replacing it.

For discovery configuration and caching, see [Installation](installation.md).

## Two ways to register a field

**Class-based.** Classes extending Rebing's `Type`, `Query`, or `Mutation` are discovered and registered in the default
schema without an attribute. Existing Rebing code keeps working, and you can adopt attributes gradually.

**Action-based.** A method carrying `#[Query]` or `#[Mutation]` becomes a field on its own, with the resolver arguments
taken from the method signature. This is the preferred style for new code, and the rest of this page describes it.

## `#[Query]` and `#[Mutation]`

Both attributes target methods and take the same arguments.

| Parameter     | Type      | Default            | Purpose                                                                       |
|---------------|-----------|--------------------|-------------------------------------------------------------------------------|
| `name`        | `?string` | the method name    | The field name in the schema.                                                 |
| `type`        | `?string` | inferred           | The GraphQL type returned. Required unless the return type is scalar or void. |
| `schema`      | `?string` | `graphql.default_schema` | The schema this field is registered in. See [Schemas](#schemas).        |
| `description` | `?string` | `null`             | Surfaced as the field description in GraphiQL.                                |
| `list`        | `bool`    | `false`            | Wrap the type in a GraphQL list of non-null elements.                         |
| `nullable`    | `bool`    | `false`            | Allow the field to resolve to `null`.                                         |

`list: true` with the default `nullable: false` produces `[Product!]!`: a non-null list of non-null elements. Setting
`nullable: true`, or returning `?array`, makes the list itself nullable.

```php
#[Query(name: 'productCount', description: 'Number of products in stock')]
public function count(): int
{
    return Product::query()->count();
}
```

### Return types

A scalar return type is mapped for you: `string`, `int`, `float`, and `bool` become the matching GraphQL scalar. A
`void` return becomes a `Null` scalar, which suits a mutation that reports nothing back.

Anything else needs `type:` naming a registered GraphQL type. Discovery throws a `RuntimeException` when it cannot infer
a type and none was given, so a missing type is reported at boot rather than at query time.

A nullable return type (`?string`, `?Product`) makes the field nullable, whether the type was inferred or given through
`type:`. Inference only ever widens: `nullable: true` on the attribute stands even when the return type is not nullable,
and a method with no declared return type leaves the field non-null.

```php
#[Query(type: 'Product')]
public function product(#[Arg('id')] Product $product): Product
{
    return $product;
}
```

Note that `type:` describes the GraphQL type, and the PHP return type stays whatever your code returns.

## Arguments

Every parameter becomes a GraphQL argument unless it is one of the injections described below. A scalar parameter needs
no attribute; its GraphQL type comes from the PHP type, and the argument is nullable when the parameter is nullable or
has a default value.

```php
#[Query(type: 'Order', list: true)]
public function orders(string $status, int $limit = 25): array
{
    // status: String!   limit: Int
}
```

`#[Arg]` renames an argument, sets its GraphQL type, documents it, or attaches validation rules.

| Parameter           | Type                            | Purpose                                                                 |
|---------------------|---------------------------------|--------------------------------------------------------------------------|
| `name`              | `?string`                       | The argument name, when it should differ from the parameter name.        |
| `type`              | `?string`                       | The GraphQL type. Required for a parameter that is not scalar.           |
| `rules`             | `array\|Closure\|null`          | Validation rules, evaluated per request when a closure is given.         |
| `description`       | `?string`                       | Surfaced in GraphiQL.                                                    |
| `deprecationReason` | `?string`                       | Marks the argument deprecated.                                           |

```php
#[Mutation(type: 'Order')]
public function updateOrderStatus(
    #[Arg('id')] Order $order,
    #[Arg(rules: ['in:pending,paid,shipped'], description: 'The new status')] string $status,
): Order {
    $order->update(['status' => $status]);

    return $order;
}
```

Rules given here are merged with anything contributed by a rules provider, so `#[Arg(rules:)]` and attribute-based
validation coexist. See [GraphQL argument validation and hydration](graphql-arguments.md).

## Model binding

A parameter typed as an Eloquent model is not treated as a regular argument. The schema exposes an `ID` argument
instead, and the resolver receives the model.

```php
#[Query(type: 'Order')]
public function order(#[Arg('id')] Order $order): Order
{
    return $order;
}
```

The lookup always uses the model's route key (`getRouteKeyName()`), the way Laravel's own route model binding does, so
a model exposing a UUID is never addressable by its primary key.

Nullability decides what a missing record means. A non-nullable binding adds a `Rule::exists` check on the route key
and fails validation when nothing matches. A nullable binding (`?Order $order = null`) adds no `exists` rule and
resolves to `null`, which is what you want for a field that may legitimately return nothing.

`#[Arg]` on a bound model renames the argument, adds rules, or overrides the `ID` type. Authorizing the bound record is
covered in [GraphQL authorization](graphql-authorization.md).

## Resolver injections

Three values from Rebing's resolver signature can be pulled into the method, and they are excluded from the generated
arguments.

| Parameter                                     | Receives                                                        |
|-----------------------------------------------|-----------------------------------------------------------------|
| `#[Root] mixed $root`                         | The parent object. Top-level queries see `null`.                 |
| `#[Context] mixed $context`                   | Rebing's context value.                                          |
| `GraphQL\Type\Definition\ResolveInfo $info`   | The webonyx resolve info, detected by type with no attribute.    |

```php
use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;

#[Query(type: 'Invoice', list: true)]
public function invoices(#[Root] mixed $customer, #[Context] mixed $context, ResolveInfo $info): array
{
    // ...
}
```

Class-typed parameters that are none of the above are resolved from the service container, so a resolver can take its
dependencies directly. Laravel's own contextual attributes (`#[CurrentUser]`, `#[Config]`, and friends) are left alone
at discovery so the container resolves them through their own hooks.

## Schemas

`#[Schema('name')]` routes an action to a named schema instead of the default one. It targets classes and methods, and
the closest declaration wins: an explicit `#[Query(schema: '...')]` beats a method-level `#[Schema]`, which beats a
class-level one.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
class Maintenance
{
    #[Query(type: 'Job', list: true)]
    public function failedJobs(): array
    {
        // lands in the admin schema
    }

    #[Query]
    #[Schema('reports')]
    public function queueDepth(): int
    {
        // lands in the reports schema
    }
}
```

Class-based registrations always land in the default schema, since there is no action to decorate.

## Middleware

`#[Middleware]` attaches Rebing middleware to a field. It is repeatable and targets classes and methods, and takes one
class string or a list of them. Class-level middleware is applied first, so it wraps method-level middleware.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;

#[Middleware(LogGraphQLCalls::class)]
class Orders
{
    #[Query(type: 'Order', list: true)]
    #[Middleware([ThrottlePerTenant::class, TrackUsage::class])]
    public function orders(): array
    {
        // ...
    }
}
```

These are Rebing's own middleware (`Rebing\GraphQL\Support\Middleware`), running through its
`Pipeline::send($arguments)->through($middleware)->via('resolve')` chain alongside any global middleware, so
`terminate()` hooks keep working.

## Pagination and sorting

`#[Paginated]` turns a field into a Rebing paginated type and provides `page` and `limit` arguments. It requires an
explicit object type on the action, since there is nothing to paginate over otherwise. The matching `Pagination` value
object is injectable and is invokable on a query builder.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;

#[Query(type: 'Product')]
#[Paginated(defaultLimit: 50)]
public function products(Pagination $pagination): LengthAwarePaginator
{
    return $pagination(Product::query());
}
```

`#[Sortable]` provides sorting arguments over a fixed list of fields, and injects a `Sort` value object that applies the
ordering to a builder. By default it exposes `sortBy` and `sortDirection`; `unified: true` exposes a single `order`
argument taking values such as `name:desc` instead.

`defaultField:` decides what a request that asks for no sorting gets. It becomes the argument's GraphQL default, so it
shows up in the schema and arrives in the resolver like any other value, and `Sort->field` is never null. Without it the
argument stays optional and `Sort->field` is `null` until the caller sorts.

Both defaults are checked while the schema is built: a `defaultField` outside `fields`, or a `defaultDirection` that is
neither `asc` nor `desc`, throws a `LogicException` at discovery rather than producing an argument whose own `in:` rule
rejects its default.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

#[Query(type: 'Product', list: true)]
#[Sortable(fields: ['name', 'price', 'updated_at'], defaultField: 'name', defaultDirection: 'desc')]
public function products(Sort $sort): array
{
    return $sort(Product::query())->get()->all();
}
```

Both attributes validate their own arguments, so an unknown sort field is rejected before the resolver runs. They are
`ActionArgProvider` implementations, which is the same hook your own attributes can use to contribute arguments and
value objects.

## Deprecation

Mark a field with PHP's native `#[\Deprecated]` and an argument with `#[Arg(deprecationReason:)]`. Both surface as
GraphQL's `deprecationReason`.

```php
#[Query(type: 'Product', list: true)]
#[\Deprecated(message: 'Use products instead', since: '2.3.0')]
public function allProducts(): array
{
    // deprecationReason: "Use products instead (since 2.3.0)"
}
```

A `message` and a `since` are combined as `"{message} (since {since})"`. Giving only one of them uses that one, and a
bare `#[\Deprecated]` reads `Deprecated`. PHP's attribute cannot target parameters, which is why arguments carry their
reason on `#[Arg]`.

## Caching

Discovered actions are cached with the rest of discovery. See [Installation](installation.md) for
`php artisan discovery:cache` and the environments it applies to.

One detail is specific to GraphQL: when the application's configuration is cached
(`php artisan config:cache`), the schema configuration is not rewritten, so the cached configuration wins. The field
bindings themselves are still registered, so a cached configuration and freshly discovered actions stay consistent.
Rebuild both when you change a field:

```bash
php artisan optimize
```

## Where to next

- [GraphQL authorization](graphql-authorization.md): `#[Authorize]`, gates, and authorizing a bound model.
- [GraphQL argument validation and hydration](graphql-arguments.md): validating arguments and hydrating value objects,
  and the hooks for wiring in your own library.
- [Validation](validation.md): the attribute-based rules that back argument validation.
