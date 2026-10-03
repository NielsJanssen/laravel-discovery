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

**Class-based.** Classes extending Rebing's `Query` or `Mutation` are discovered and registered in the default
schema without an attribute. Classes extending Rebing's `Type` go to `graphql.types`, so every schema can use them.
Existing Rebing code keeps working, and you can adopt attributes gradually.

**Action-based.** A method carrying `#[Query]` or `#[Mutation]` becomes a field on its own, with the resolver arguments
taken from the method signature. This is the preferred style for new code, and the rest of this page describes it.

## `#[Query]` and `#[Mutation]`

Both attributes target methods and take the same arguments.

| Parameter       | Type      | Default                  | Purpose                                                                        |
|-----------------|-----------|--------------------------|--------------------------------------------------------------------------------|
| `name`          | `?string` | the method name          | The field name in the schema.                                                  |
| `type`          | `?string` | inferred                 | The GraphQL type returned. Required unless the return type can be inferred.    |
| `schema`        | `?string` | `graphql.default_schema` | The schema this field is registered in. See [Schemas](#schemas).               |
| `description`   | `?string` | `null`                   | Surfaced as the field description in GraphiQL.                                 |
| `list`          | `bool`    | `false`                  | Wrap the type in a GraphQL list of non-null elements.                          |
| `nullable`      | `bool`    | `false`                  | Allow the field to resolve to `null`.                                          |
| `of`            | `?string` | `null`                   | The item type of a list. Implies `list: true`; cannot be combined with `type`. |
| `nullableItems` | `bool`    | `false`                  | Allow list items to be `null`: `[Product]!` instead of `[Product!]!`.          |

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

A [type mapper](#type-mappers) gets the first say on a return type without `type:` or `of:`. After that, a scalar
return type is mapped for you: `string`, `int`, `float`, and `bool` become the matching GraphQL scalar. A
`void` return becomes a `Null` scalar, which suits a mutation that reports nothing back.

A return type that is a [`#[Type]` class](#object-types) is inferred as that type, so `public function book(): Book`
needs no `type:`. The class only has to be discovered somewhere; the order in which classes are discovered does not
matter. A PHP enum return type is inferred as a [GraphQL enum](#enums). `array`, `iterable` and `Collection` returns
need `of:` to name the type of their items.

Anything else needs `type:` naming a registered GraphQL type. Discovery throws a `RuntimeException` when it cannot infer
a type and none was given (`mixed`, a PHP union, `array` without `of:`), so a missing type is reported at boot rather
than at query time.

A nullable return type (`?string`, `?Book`) makes the field nullable, whether the type was inferred or given through
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

`type:` and `of:` take a GraphQL type name (`'Product'`), a scalar name (`'string'`, `'ID'`) or a class-string
(`Product::class`). A class-string resolves to the GraphQL type that class is registered as in the
`NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry`, which every `#[Type]` class and every enum is added to. A class-string that
is not registered, whether given or inferred, on a query, a mutation or a `#[Type]` field, makes discovery throw a
`LogicException` naming the method or field and the class. The same goes for a class-string in `#[Arg(type:)]`, which
must be registered as an input type (an enum is; a `#[Type]` class is not). The check runs when discovery boots,
whether or not the configuration is cached.

```php
#[Query(of: 'Product', nullableItems: true)]
public function shelf(): array
{
    return [Product::first(), null];   // [Product]!
}
```

## Object types

`#[Type]` on a plain class makes it a GraphQL object type. Its public properties become fields, and so do methods that
carry `#[Field]`. Return an instance from a query, and the return type tells discovery which GraphQL type it is:

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(description: 'A published book')]
final class Book
{
    public function __construct(
        #[Field(type: 'ID')] public string $id,
        public string $title,
        public ?string $subtitle,
        public AuthorSummary $author,                 // another #[Type] class
        #[Field(of: 'string')] public array $tags = [],
        #[Field(description: 'ISBN-13', deprecationReason: 'Use identifiers')] public ?string $isbn = null,
        #[Ignore] public string $internalNotes = '',
    ) {}

    public string $slug { get => Str::slug($this->title); }

    #[Field(description: 'The title, shortened')]
    public function excerpt(int $length = 80): string { return Str::limit($this->title, $length); }

    #[Field(of: Book::class)]
    public function related(Recommender $recommender, int $limit = 5): array { return $recommender->for($this, $limit); }
}

class Books
{
    #[Query]
    public function book(): Book { /* ... */ }                 // book: Book!

    #[Query(of: Book::class)]
    public function books(): array { /* ... */ }               // books: [Book!]!
}
```

```graphql
"A published book"
type Book {
  id: ID!
  title: String!
  subtitle: String
  author: AuthorSummary!
  tags: [String!]!
  "ISBN-13"
  isbn: String @deprecated(reason: "Use identifiers")
  slug: String!
  "The title, shortened"
  excerpt(length: Int = 80): String!
  related(limit: Int = 5): [Book!]!
}
```

The type is named after the class, with a `Type` suffix dropped (`BookType` becomes `Book`). `#[Type(name: ...)]`
overrides it. Two types with the same name, including a hand-written Rebing type, are an error.

**Fields.** Every public, non-static property is a field: promoted, plain, and hooked properties with a `get` hook.
`#[Ignore]` leaves one out. A public method is a field only with `#[Field]`; its parameters work as they do on a query:
scalars and enums become arguments (with their defaults), `#[Root]`, `#[Context]` and `ResolveInfo` are injected, and any other
class is resolved from the container. The method is called on the object being resolved, never on a fresh instance.

`#[Field]` takes:

| Parameter           | Type      | Default         | Purpose                                                                        |
|---------------------|-----------|-----------------|--------------------------------------------------------------------------------|
| `name`              | `?string` | the member name | The field name in the schema.                                                  |
| `type`              | `?string` | inferred        | A GraphQL type name, a scalar name or a class-string.                          |
| `of`                | `?string` | `null`          | The item type of a list; cannot be combined with `type`.                       |
| `nullable`          | `bool`    | `false`         | Makes the field nullable. It only widens: `false` keeps a `?T` field nullable. |
| `nullableItems`     | `bool`    | `false`         | Allow list items to be `null`.                                                 |
| `description`       | `?string` | `null`          | The field description.                                                         |
| `deprecationReason` | `?string` | `null`          | Marks the field deprecated. On methods, native `#[\Deprecated]` works too.     |

**Inferred types.** A [type mapper](#type-mappers) is asked first. Then `string`, `int`, `float` and `bool` map to their scalars. A PHP enum maps to a
[GraphQL enum](#enums). A class maps to the GraphQL type it is
registered as, which is looked up when the schema is built, so classes can reference each other in any order. A class
that is not registered is reported at boot. `?T` makes a field nullable; a default value does not. `array`, `iterable`
and `Collection` need `of:` (or `type:`), and so does anything without a GraphQL counterpart: `mixed`, no type, a
union, `void`.

**Errors at discovery.** `#[Field]` on a private, protected, static or write-only member, an action attribute such as `#[Paginated]` on a field method, `#[Field]` together with
`#[Ignore]`, a type that cannot be inferred, two fields with one name, and a field method that binds a model or sets
`#[Arg(rules:)]` all throw a `LogicException` naming the class, the member and the fix. Field arguments are not
validated yet, so validate inside the method.

**Field authorization.** `#[Authorize]` on a property or `#[Field]` method guards that one field: a denied field
resolves to `null` and is nullable in the schema. See
[Authorizing a field](graphql-authorization.md#authorizing-a-field).

**Field decorators.** An attribute that implements `FieldDecorator` adjusts the field it sits on, as `#[Authorize]`
does. Discovery collects every such attribute on a property or `#[Field]` method and calls `decorate()` when the type
is built. A decorator that also implements `FieldDiscoveryVerifier` gets `verify()` called at discovery, so it can
reject a field it does not fit:

```php
use Attribute;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDiscoveryVerifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldBlueprint;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecorator;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Uppercase implements FieldDiscoveryVerifier, FieldDecorator
{
    public function verify(string $member, DiscoveredTypeField $field): void
    {
        if ($field->type->scalar !== 'string') {
            throw new LogicException("$member has #[Uppercase] but is not a string.");
        }
    }

    public function decorate(FieldBlueprint $field): void
    {
        $field->nullable();
        $field->wrapResolver(static function (mixed $root, array $args, mixed $context, ?ResolveInfo $info, Closure $next): ?string {
            $value = $next($root, $args, $context, $info);

            return is_string($value) && $value !== '' ? strtoupper($value) : null;
        });
    }
}
```

`FieldBlueprint` offers `nullable()` to make the field nullable, `wrapResolver()` to run code around the resolver,
and `addPrivacy()` to add a check that resolves the field to `null` without running the resolver (Rebing's `privacy`).
Its `app` property is the application, for resolving services. Decorators run in declaration order, which sets the
order at resolve time: every privacy check runs first, in declaration order, and the field is `null` as soon as one
fails. Then the resolver wrappers run, the last-declared one outermost, so it sees the call first and the result last.
A decorator is cached with discovery when it serializes; one that holds a closure is read again from the attribute when
the type is built. A `FieldDecorator` on a method without `#[Field]`, `#[Query]` or `#[Mutation]`, or on a property that
is not a field, is an error.

### Eloquent models

`#[Type]` works on an Eloquent model too. Its fields come from virtual hooked properties that read the model's
attributes, and from `#[Field]` methods:

```php
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Type]
class Article extends Model
{
    #[Field(type: 'ID')]
    public int $id { get => $this->getKey(); }

    public string $title {
        get => $this->getAttribute('title');
        set(string $value) { $this->setAttribute('title', $value); }
    }

    #[Field(type: 'String')]
    public ?CarbonImmutable $publishedAt { get => $this->getAttribute('published_at'); }

    public ArticleStatus $status { get => $this->getAttribute('status'); }   // a PHP enum

    #[Authorize('viewSales')]
    public int $copiesSold { get => $this->getAttribute('copies_sold'); }

    #[Field(description: 'The title in capitals')]
    public function headline(): string { return Str::upper($this->title); }

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'status' => ArticleStatus::class,
            'copies_sold' => 'integer',
        ];
    }
}

class Articles
{
    #[Query]
    public function article(#[Arg('id')] Article $article): Article { return $article; }

    #[Mutation]
    public function retitleArticle(#[Arg('id')] Article $article, string $title): Article
    {
        $article->title = $title;   // the set hook marks the attribute dirty
        $article->save();

        return $article;
    }
}
```

```graphql
type Article {
  id: ID!
  title: String!
  publishedAt: String
  status: ArticleStatus!
  copiesSold: Int          # nullable because of #[Authorize]
  "The title in capitals"
  headline: String!
}

enum ArticleStatus {
  Draft
  Published
}

type Query {
  article(id: ID!): Article!
}

type Mutation {
  retitleArticle(title: String!, id: ID!): Article!
}
```

- **Framework members are skipped.** Anything declared in a class or trait under the `Illuminate\` namespace, such as
  `$exists`, `$timestamps`, `$incrementing` and `$wasRecentlyCreated`, never becomes a field, also when the model
  redeclares it (`public $timestamps = false;`). This holds for any `#[Type]` class, so a class using
  `Illuminate\Bus\Queueable` does not expose `$queue` either.
- **Hooks must be virtual.** A hook reads and writes through `getAttribute()` and `setAttribute()`, so the casts, the
  dirty tracking, `toArray()` and `save()` keep working. A plain public property declared in the model itself would
  shadow the attribute of the same name, so discovery rejects it with a `LogicException`. Make it a virtual hooked
  property, or mark it `#[Ignore]` when it is deliberately not an attribute (a transient flag, say); an ignored
  property is left alone.
- **Traits.** A hooked property from a trait is a field like any other. A plain public property from a trait, such as
  a package's bookkeeping property, is skipped rather than rejected, since you cannot add `#[Ignore]` to vendor code.
- **Casts and decorators.** A cast attribute reads through the hook as its cast value, so an enum cast gives a
  [GraphQL enum](#enums). Field decorators such as `#[Authorize]` work on hooked properties and receive the model as
  the root. A decorator on a property that is skipped (a framework property, or a plain one from a trait) is an error.
- **Models in input position stay bindings.** A model parameter is still an `ID` argument with a route-key lookup, as
  described under [Model binding](#model-binding), even when the model is a `#[Type]`. Only the return type uses the
  object type.
- **Dates need a GraphQL type.** `CarbonImmutable` has no GraphQL counterpart by default: map it once in
  [the scalar map](#the-scalar-map), or name one per field with `#[Field(type: ...)]`.
- Discovery reads the model through reflection only: it never instantiates the model or queries the database.

## Enums

Any PHP enum, backed or not, becomes a GraphQL enum as soon as a query, a mutation, a `#[Type]` field or an argument
uses it. It is named after the enum, and its values are the case names. `#[Enum]` on the enum renames or describes it,
and registers it even when nothing references it. `#[EnumValue]` describes a case, and a native `#[\Deprecated]` on a
case deprecates that value.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Enum;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\EnumValue;

#[Enum(description: 'Shelf a book is filed under')]
enum Genre: string
{
    case Fiction = 'fiction';
    #[EnumValue(description: 'Biographies, essays, history')]
    case NonFiction = 'non_fiction';
    #[\Deprecated('Use Fiction')]
    case Novel = 'novel';
}

class Books
{
    #[Query(of: Book::class)]
    public function byGenre(Genre $genre): array  // byGenre(genre: Genre!): [Book!]!
    {
        return Book::query()->where('genre', $genre)->get()->all();
    }
}
```

```graphql
"Shelf a book is filed under"
enum Genre {
  Fiction
  "Biographies, essays, history"
  NonFiction
  Novel @deprecated(reason: "Use Fiction")
}
```

| Attribute      | Target    | Parameters                | Purpose                                                   |
|----------------|-----------|---------------------------|-----------------------------------------------------------|
| `#[Enum]`      | enum      | `name`, `description`     | Renames or describes the enum; registers it unreferenced. |
| `#[EnumValue]` | enum case | `description`             | Describes one value.                                      |

The GraphQL value is always the case name, also for a backed enum: `Genre::NonFiction` is `NonFiction`, not
`non_fiction`. In both directions the PHP side is the case itself: a resolver or a property returns `Genre::Fiction`,
and an enum argument reaches the resolver as a `Genre` case, never as a string. Values keep the order of the cases.
`#[\Deprecated(message:, since:)]` becomes `"{message} (since {since})"`, as it does on a query.

An enum is registered once, however many places use it and whichever is discovered first. `#[Enum]` on a class that
is not an enum, or an enum name that another type already uses (two enums called `Status` in different namespaces,
say), throws a `LogicException` when discovery boots. Rename one with `#[Enum(name: ...)]`.

To keep a hand-written Rebing `EnumType` for an enum instead, register the enum in the `TypeRegistry` yourself, under
the name of that type. Discovery then leaves the enum to it. The registration has to run before discovery boots: in the
`boot()` of a service provider that boots before `NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider`, or in a
`register()` method.

```php
public function boot(): void
{
    $this->app->make(TypeRegistry::class)->register(Genre::class, 'Genre', TypeKind::Enum);
}
```

## Type mappers

A type mapper decides the GraphQL type of a PHP type before the built-in inference does. Use one for value objects and
dates, or for a convention such as "a property named `id` is an `ID`". Inference never infers `ID` on its own: a
`string $id` is a `String!` until a mapper says otherwise.

### The scalar map

The package ships one mapper, which reads `discovery.graphql.scalars`. Each entry maps a class to a GraphQL scalar or
type name. Classes are matched with `is_a()`, so an interface entry covers every class that implements it:

Publish the config file to set it:

```bash
php artisan vendor:publish --tag=discovery-graphql-config
```

```php
// config/discovery-graphql.php
return [
    'scalars' => [
        CarbonInterface::class => 'DateTime',
    ],
];
```

A `graphql` key in `config/discovery.php` works too, and wins over the published file. With that entry, `public CarbonImmutable $publishedAt` becomes `publishedAt: DateTime!`, and so does a
`CarbonImmutable` return or an `#[Arg] CarbonImmutable $since` argument. The first matching entry wins. A name that is
not a built-in scalar (`DateTime` here) must be a type you register with Rebing yourself, usually a custom scalar in
`graphql.types` that serializes the value and parses it back into the PHP class. The map is empty by default. A key
that is not a class or interface, or a value that is not a type name, throws a `LogicException`.

### Writing your own mapper

Implement `TypeMapper` and tag it in a service provider's `register()` method:

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

final class IdsAreIds implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->name === 'id' && in_array($type->getName(), ['int', 'string'], true)
            ? TypeRef::scalar('ID', nullable: $type->isNullable())
            : null;
    }
}

$this->app->tag([IdsAreIds::class], TypeMapper::TAG);
```

Return `null` for anything the mapper does not handle. Mappers are asked in tag order, and the first one that returns a
`TypeRef` wins. The scalar map is asked last, after every tagged mapper, so your own mapper can take over a class the
scalar map also covers.

A mapper sees the type as PHP reflection reports it. A union arrives whole (`$type->isUnion()`, `$type->split()`),
`static` arrives as `static`, and `self` arrives as the class name. Untyped and `mixed` members never reach a mapper,
so they keep their discovery error.

`Member` describes what is being typed:

| Property         | Type           | Holds                                                                    |
|------------------|----------------|--------------------------------------------------------------------------|
| `name`           | `string`       | The property, method or parameter name.                                  |
| `declaringClass` | `class-string` | The class that declares the member.                                      |
| `position`       | `Position`     | `Output` for fields and returns, `Input` for arguments.                  |
| `kind`           | `MemberKind`   | `Property`, `MethodReturn` (fields and actions) or `Parameter`.          |

A `TypeRef` can name a scalar (`TypeRef::scalar('String')`), a GraphQL type by name (`TypeRef::named('DateTime')`) or a
class (`TypeRef::class(Status::class)`). A class must be registered, as an inferred one must; a PHP enum named this way
is registered as a [GraphQL enum](#enums), even when nothing else refers to it. Pass `list: true` (and
`nullableItems: true`) for a list, as in `TypeRef::named('Money', list: true)` for an `array` return.

**Where mappers run.** On `#[Type]` properties and `#[Field]` method returns, on arguments of field methods, queries and
mutations, and on query and mutation returns. An explicit type always wins: a mapper is not asked when `#[Field]` sets
`type:` or `of:`, when `#[Arg]` sets `type:`, or when `#[Query]` or `#[Mutation]` sets `type:` or `of:` or the method
has a type builder such as `#[Paginated]`.

A mapper only types a parameter that is already an argument. Model binding, `#[Root]`, `#[Context]`, `ResolveInfo`,
value objects built from args, and container injection are decided first. A class-typed parameter without `#[Arg]` is
resolved from the container, so put `#[Arg]` on a parameter like `CarbonImmutable $since` to make it an argument.

**Nullability** only widens, as with inference. The field or argument is nullable when the mapper returns a nullable
`TypeRef`, when the PHP type is nullable, when `nullable: true` is set on the attribute, or, for an argument, when the
parameter has a default value. A mapper cannot make a `?T` member non-null. A nullable mapping for a parameter that
accepts no `null` (`string $notes`, no default) throws a `LogicException` at discovery, since the resolver could not
take the `null` the schema allows.

The value an argument receives is what the GraphQL type produces: an `ID` arrives as a string, and a custom scalar
arrives as whatever its `parseValue()` returns. Type the parameter to match.

**Caching.** Mappers run at discovery time, and their results are cached with the discovery items. Decide from the
reflection alone, and run `php artisan discovery:clear` after changing a mapper or the scalar map in an environment
that caches discovery.

## Arguments

Every parameter becomes a GraphQL argument unless it is one of the injections described below. A scalar or enum
parameter needs no attribute; its GraphQL type comes from the PHP type, and the argument is nullable when the parameter
is nullable or has a default value. An enum parameter becomes an argument of that [enum](#enums) and receives the case.
A [type mapper](#type-mappers) can give an argument a different type, and it can type a class parameter that carries
`#[Arg]`, such as a date.

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
| `type`              | `?string`                       | The GraphQL type. Required for a class no type mapper handles.           |
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

Rules on an enum argument see the case, not its name, because GraphQL has already turned the value into a case by the
time validation runs. String rules such as `in:Calm,Cheerful` therefore fail. Use `Rule::enum()`, which accepts a case.
It is a method call, so it goes in a closure:

```php
#[Query]
public function calmOnly(
    #[Arg(rules: static fn(): array => [Rule::enum(Mood::class)->only([Mood::Calm, Mood::Cheerful])])]
    Mood $mood,
): string {
    return $mood->name;
}
```

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

Class-based `Query` and `Mutation` registrations always land in the default schema, since there is no action to
decorate. Class-based `Type` registrations go to `graphql.types`, so every schema can use them.

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
explicit object type on the action, since there is nothing to paginate over otherwise. The paginated type is non-null,
unless the action sets `nullable: true` or returns a nullable type. The matching `Pagination` value object is
injectable and is invokable on a query builder.

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

- [GraphQL authorization](graphql-authorization.md): `#[Authorize]`, gates, authorizing a bound model, and field
  authorization.
- [GraphQL argument validation and hydration](graphql-arguments.md): validating arguments and hydrating value objects,
  and the hooks for wiring in your own library.
- [Validation](validation.md): the attribute-based rules that back argument validation.
