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

## Configuration

The package's own settings live under `discovery.graphql`. Publish the config file to change them:

```bash
php artisan vendor:publish --tag=discovery-graphql-config
```

```php
// config/discovery-graphql.php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;

return [
    'scalars' => [],                                   // see "The scalar map" below
    'skip_namespaces' => ['Illuminate\\', 'Acme\\'],
    'naming' => [                                      // see "Naming" below
        'fields' => FieldCase::Preserve,
        'arguments' => FieldCase::Preserve,
        'operations' => FieldCase::Preserve,
    ],
];
```

A `graphql` key in `config/discovery.php` works too, and wins over the published file.

| Key               | Default             | Purpose                                                                                      |
|-------------------|---------------------|----------------------------------------------------------------------------------------------|
| `scalars`         | `[]`                | GraphQL scalar or type names for PHP classes; see [the scalar map](#the-scalar-map).          |
| `skip_namespaces` | `['Illuminate\\']`  | Namespace prefixes whose members never become fields of a `#[Type]` or `#[Input]`.            |
| `naming`          | `Preserve` for all  | How PHP names become field, argument and operation names; see [Naming](#naming).              |

`skip_namespaces` covers anything declared in a class or trait under one of the prefixes, also when an application
class redeclares the property. Add a package your types extend, so its base model's public members stay out of the
schema. The list replaces the default, so keep `Illuminate\` in it. A trailing backslash is optional; an entry that is
not a non-empty string throws a `LogicException`. Like everything discovery reads, the result is cached with discovery,
so run `php artisan discovery:clear` after changing it.

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
| `name`          | `?string` | the method name          | The field name in the schema, used as written. See [Naming](#naming).          |
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
carry `#[Field]`. `#[Type]` takes `name:`, `description:` and `naming:`, a [naming strategy](#naming) for this type's
fields and `#[Field]` method args. Return an instance from a query, and the return type tells discovery which GraphQL
type it is:

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
| `rules`             | `array\|Closure\|null` | `null` | Validation rules, only on a property of an [`#[Input]`](#input-types).   |

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
`resolveWith()` to replace where the value comes from while every wrapper still runs around it, and `addPrivacy()` to
add a check that resolves the field to `null` without running the resolver (Rebing's `privacy`).
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

- **Framework members are skipped.** Anything declared in a class or trait under a skipped namespace, such as
  `$exists`, `$timestamps`, `$incrementing` and `$wasRecentlyCreated`, never becomes a field, also when the model
  redeclares it (`public $timestamps = false;`). This holds for any `#[Type]` or `#[Input]` class, so a class using
  `Illuminate\Bus\Queueable` does not expose `$queue` either. The skipped namespaces are `Illuminate\` by default; see
  [Configuration](#configuration) to add a package your types extend.
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

### Batch loading

A field that loads related data per parent runs one query per parent: a list of 50 authors with their books is 51
queries. A batched field collects every parent the query reaches at one level and loads them together, so the same
list is two queries.

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\KeyLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;

#[Type]
class Author extends Model
{
    public string $name { get => $this->getAttribute('name'); }

    #[Field(of: Book::class), Relation]                              // relation 'books'
    public function books(): HasMany { return $this->hasMany(Book::class); }
}

#[Type]
class Book extends Model
{
    #[Field(name: 'writer', type: Author::class), Relation]          // relation 'author', field 'writer'
    public function author(): BelongsTo { return $this->belongsTo(Author::class); }
}

#[Type]
final class Review
{
    public function __construct(public int $authorId) {}

    #[Load(KeyLoader::class, model: Author::class, key: 'authorId')]
    public ?Author $author = null;

    #[Load(KeyLoader::class, model: Book::class, key: 'authorId', column: 'author_id', many: true), Field(of: Book::class)]
    public array $books = [];
}
```

- **`#[Relation]`** eager loads an Eloquent relation on every parent that has not loaded it yet, one query per parent
  class, and reads it with `getRelation()`. The relation defaults to the PHP method name, also when `#[Field(name:)]`
  renames the field; `#[Relation('author')]` names another. A relation loaded beforehand, by `with()` for example, is
  not loaded again. The relation method itself is only called by Eloquent, never as a resolver.
- **`#[Relation]` fields need `type:` or `of:`** on their `#[Field]`: `type:` for a single record, `of:` for a list.
  The type is never read from the relation, so discovery never instantiates a model.
- **`#[Load(LoaderClass::class, ...)]`** resolves the field through any `BatchLoader`. The named arguments after the
  loader become its options.
- **`KeyLoader`** loads `model:` records whose `column:` (default: the model's key name) matches the parent's `key:`
  property, which must be public. A parent without a match gets `null`; with `many: true` it gets every match, or an
  empty list. Without `many: true`, `column:` must be the model's key, since only that is known to be unique.
- **Field arguments** reach the loader as `$args`, and a field selected twice with different arguments loads twice:
  a batch is one loader, one set of options and one set of arguments.

**Your own loader.** A `BatchLoader` gets the parents, each once, and returns one result per parent, in the same order:

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;

final class FileLoader implements BatchLoader
{
    public function __construct(private readonly MediaLibrary $media) {}

    public function load(array $roots, array $options, array $args): array
    {
        $files = $this->media->forOwners($roots, $options['collection']);

        return array_map(fn(object $root): array => $files[$root->id] ?? [], $roots);
    }
}
```

Loaders are resolved from the container. A result list of another length, or with other keys, is a `LogicException`.
A `null` for a non-null field is a field error; make the field nullable when a parent can lack a value.

**Your own attribute.** Implement `BatchedFieldDecorator` and `use ResolvesThroughBatchLoader`, which supplies `decorate()`:

```php
use Attribute;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchedFieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\ResolvesThroughBatchLoader;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Files implements BatchedFieldDecorator
{
    use ResolvesThroughBatchLoader;

    public function __construct(public string $collection = 'default') {}

    public function loader(): string { return FileLoader::class; }

    public function options(string $fieldName): array { return ['collection' => $this->collection]; }
}

#[Field(of: 'String'), Files('covers')]
public function covers(?string $size = null): array { return []; }   // $size is an argument, passed as $args
```

`options()` receives the PHP member name. A loader that also implements `VerifiesLoadOptions` checks its options at
discovery, as `KeyLoader` and `RelationLoader` do.

**Inside a field method.** For a case an attribute cannot express, take `Loaders` as a parameter and defer to a loader
yourself. The method returns a `Deferred`, so name the field's type with `type:` or `of:`:

```php
use GraphQL\Deferred;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Loaders;

#[Field(type: Author::class, nullable: true)]
public function reviewer(Loaders $loaders): Deferred
{
    return $loaders->defer(KeyLoader::class, $this, ['model' => Author::class, 'key' => 'reviewerId']);
}
```

`defer()` joins the same batch as a `#[Load]` with that loader and those options.

**One registry per execution.** Batches live in a `Loaders` registry that every GraphQL execution gets fresh, through
`LoadersExecutionMiddleware`. The package adds it in front of `graphql.execution_middleware` and of every schema's own
`execution_middleware` list once the application has booted, so a batch never carries over to the next request, also
in tests and long-running workers. Resolving `Loaders` outside an execution throws a `LogicException`. A schema list
set at runtime, after boot, needs the middleware added by hand.

**Composing with other decorators.** `#[Authorize]` runs per parent before the parent joins a batch, in either
attribute order, so a denied parent never reaches the loader. A decorator's `wrapResolver()` around a batched field
receives a `Deferred` from `$next`; chain on it with `->then()` rather than reading the value directly.

**Errors at discovery.** `#[Relation]` without `type:` or `of:`, on a class that is not a model, or naming a method the
model lacks; a `KeyLoader` without `model:` or `key:`, with an unknown option, with a `key:` that is not a public
property of a plain `#[Type]` class, whose `many:` does not match a list field, or with a `column:` other than the key
without `many: true`; an option that is not named or does not serialize (a closure); a loader that does not implement
`BatchLoader` or cannot be instantiated; and two batched attributes on one field. Each
is a `LogicException` naming the class, the member and the fix. Selection-aware eager loading (Rebing's
`SelectFields`) is not supported.

### Dynamic fields with a type factory

When a type's fields are only known at runtime, such as a model whose attributes come from a configuration table, name a
`TypeFactory` on the type. Its fields are added to the ones the class declares.

```php
#[Type(factory: AcmeCustomFields::class)]
final class AcmeCustomer
{
    public string $name = '';

    #[Ignore]
    public array $custom = [];
}

final readonly class AcmeCustomFields implements TypeFactory
{
    public function __construct(private AcmeFieldDefinitions $definitions) {}

    public function fields(TypeContext $context): iterable
    {
        foreach ($this->definitions->for($context->class) as $definition) {
            yield new Field(
                name: $definition->name,
                type: 'string',
                nullable: true,
                args: ['format' => new Field(type: 'string', nullable: true)],
                resolve: fn (AcmeCustomer $root, array $args): ?string => $definition->format($root->custom[$definition->name] ?? null, $args['format'] ?? null),
            );
        }
    }
}
```

- The factory is resolved from the container when Rebing builds the type, so it takes constructor dependencies. It
  never runs during discovery, and what it yields is never written to the discovery cache: only the class name is.
- `TypeContext` holds `name` and `class` of the type, `kind` (`Position::Output`), `naming` (the strategy for the type's
  own fields) and `declaredFields`, the GraphQL names of the fields the class declares.
- A factory field is a `Field` with a `name` and a `type:` (or `of:` for a list). `description`, `deprecationReason`,
  `nullable` and `nullableItems` apply. Without `resolve`, it reads the property of an object root or the key of an array
  root. A `resolve` closure receives `($root, array $args, $context, ResolveInfo $info)`.
- `args` maps an arg name to a `Field` that describes it (`type:` or `of:`, `nullable`, `description`,
  `deprecationReason`). The GraphQL name goes through the type's argument naming strategy, but `$args` arrive under the
  keys you declared: `regionCode` is exposed as `region_code` under snake case and still read as `$args['regionCode']`.
- A factory field types its value with a scalar, a registered class or a GraphQL type name. Discovery cannot see what a
  factory yields, so the types it names are not registered for you: an enum or input they need must be registered
  elsewhere.

**Errors.** A factory that is not a class implementing `TypeFactory`, and `#[Input(factory:)]` (factories only add
fields to output types for now), fail at discovery. `#[Field(resolve:)]` or `#[Field(args:)]` on a declared member does
too, since only a factory field can set them. At build time a field that repeats a declared name, lacks a name or type,
or sets both `type:` and `of:` is a `LogicException`, as is an arg with `rules:`: Rebing does not validate the args of
nested fields.

### Whole types from a type provider

When whole types are only known at runtime, such as one type per entry of a metadata table, implement `TypeProvider`.
Discovery finds the class by its interface; only its class name is cached.

```php
final readonly class AcmeResourceTypes implements TypeProvider
{
    public function __construct(private AcmeResources $resources) {}

    public function types(): iterable
    {
        foreach ($this->resources->all() as $resource) {
            yield new TypeDefinition(
                name: $resource->name,
                kind: Position::Output,
                fields: fn (TypeContext $context) => array_map(
                    fn ($field) => new Field(name: $field->name, type: $field->type, nullable: ! $field->required),
                    $resource->fields,
                ),
                class: $resource->class,
                description: $resource->label,
            );
        }
    }
}
```

- The provider is resolved from the container when Rebing's `GraphQL` is first resolved, so it takes constructor
  dependencies. It never runs during discovery, and its types are added to Rebing directly, never written to config.
- `fields` receives a `TypeContext` (`class` is `null` without a `class:`) and yields `Field`s, built like a type
  factory's: `type:` or `of:`, `description`, `deprecationReason`, `nullable`, and for output types `resolve` and
  `args`. Without `resolve` an object or array root is read by field name.
- `class:` maps a PHP class to the type, so `#[Query] public function shipment(): AcmeShipment` infers the provided
  type. Without it, name the type with `#[Query(type: 'Name')]` or `of:`.
- `kind: Position::Input` provides an input type. It takes no `class:`: name it with `#[Arg(type: 'Name')]` on an
  `array` parameter, and the action receives the value as a plain array keyed by field name. Input fields take no
  `resolve`, `args` or `rules`.
- With a provider present, the check that every class an action or type points at is a registered type runs when
  `GraphQL` is resolved instead of at discovery, since the provider's classes are not known before then. The error is
  the same one, naming the referrer.

**Errors.** A provider that yields anything but a `TypeDefinition`, a name already registered (by another provider, a
`#[Type]` or `graphql.types`), a class a `#[Type]` already maps, `class:` or `rules:` on an input type, `resolve` or
`args` on an input field, and the field errors of a type factory are a `LogicException`.

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

Set it in the package config; see [Configuration](#configuration):

```php
// config/discovery-graphql.php
return [
    'scalars' => [
        CarbonInterface::class => 'DateTime',
    ],
];
```

With that entry, `public CarbonImmutable $publishedAt` becomes `publishedAt: DateTime!`, and so does a
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

## Input types

`#[Input]` on a class makes it a GraphQL input object. Its public properties become the input fields, typed the way
`#[Type]` fields are, but in input position. A parameter typed as an `#[Input]` class becomes one argument of that
input type, named after the parameter, and the resolver receives an instance of the class. `#[Input]` takes `name:`,
`description:` and `naming:`, a [naming strategy](#naming) for this input's fields.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Validation\Rule\{Max, Min, Size};

#[Input]
final readonly class CreateBook
{
    public function __construct(
        #[Min(2), Max(255)] public string $title,
        public Genre $genre,
        #[Authorize('attach')] public Publisher $publisher,   // Eloquent: an ID, looked up by route key
        public ?Address $shipTo = null,
        #[Field(of: Chapter::class)] public array $chapters = [],
        #[Field(rules: ['nullable', 'date'])] public ?string $publishAt = null,
    ) {}
}

#[Type, Input]
final class Address
{
    public function __construct(public string $street, public string $city, #[Size(2)] public string $country) {}
}

final class BookMutations
{
    #[Mutation] public function createBook(CreateBook $input): Book {}
    #[Mutation] public function draftBook(#[Arg('data', description: 'Draft contents')] ?CreateBook $draft = null): Book {}
}
```

```graphql
input CreateBookInput {
  title: String!
  genre: Genre!
  publisher: ID!
  shipTo: AddressInput
  chapters: [ChapterInput!] = []
  publishAt: String
}

type Address { street: String! city: String! country: String! }
input AddressInput { street: String! city: String! country: String! }

type Mutation {
  createBook(input: CreateBookInput!): Book!
  draftBook("Draft contents" data: CreateBookInput): Book!
}
```

The input is named after the class plus `Input`, unless the name already ends in `Input`; `#[Input(name:,
description:)]` overrides both. A class may carry both `#[Type]` and `#[Input]`, and then has an output type and an
input type (`Address` and `AddressInput`). Every property of such a class must then work in both positions: a property
typed as an output-only `#[Type]` cannot be shared, and an `#[Authorize(message:)]` still needs `onDenied:
Denied::Error` for the output field.

**Fields.** Every public, non-static property is a field; `#[Ignore]` leaves one out, and a property hook needs a `set`
hook. `#[Field]` takes the same `name`, `type`, `of`, `nullable`, `nullableItems`, `description` and
`deprecationReason` as on a `#[Type]`. A field is optional when its type is nullable or it has a default value; a
scalar, enum or list default is shown in the schema. A field can be a scalar, an enum, another `#[Input]`, a list of
those through `of:`, or an Eloquent model: that last one is an `ID` field (or the `type:` you name) that is looked up by
the model's route key, with an automatic `exists` rule when it is not nullable. [Type mappers](#type-mappers) are asked
about every other property in input position, except one typed as an `#[Input]` class. Properties Laravel declares are
left out, as on a `#[Type]`.

**The argument.** `#[Arg(name:, description:)]` renames and describes an input argument; it is nullable when the
parameter is nullable or has a default. `#[Arg(type:)]` is rejected, since the type is the input type. A class without
`#[Input]` is still injected from the container, as before. A parameter that names an input by GraphQL name, such as
`#[Arg(type: 'CreateBookInput')] array $raw`, uses the input too, and receives the plain array. Model-bound
`#[Authorize]` checks run for an input however the argument reaches it: as an `#[Input]` parameter, through
`#[Arg(type:)]`, in a list, or nested in another input.

**Hydration.** The built-in `InputHydrator` calls the constructor with named arguments, then sets the remaining public
properties. It builds nested inputs, enum cases and models on the way, and a renamed field reaches the property it came
from. An absent optional field keeps the constructor or property default. A hydrator you tag yourself can claim an
`#[Input]` class first; it receives the values keyed by property name. See
[GraphQL argument validation and hydration](graphql-arguments.md).

**Validation.** Rules sit on the input type's own fields, so Rebing validates them before the resolver runs and reports
them at their full path: `input.title`, `input.shipTo.city`, `batch.reviews.1.body`. A field's rules are the
`nielsjanssen/laravel-validation` attributes on its property, `#[Field(rules:)]` (an array, or a closure that receives
the input object's values keyed by PHP property name, and as a second argument `$request`, the field's arguments as the
client sent them, keyed by GraphQL argument name), and the automatic `exists` rule of a model field. An attribute's custom `message:` is
reported at the same path. Nested laravel-validation rules (`#[Valid]`, `#[ListOf]`, `#[Each]`) on an input property
are not applied yet.

**Value keys.** Values reach each consumer under different keys:

| Consumer                                    | Keys                                                  |
|---------------------------------------------|-------------------------------------------------------|
| a `#[Field(rules:)]` closure, its first argument | PHP property names (`name`)                     |
| a `#[Field(rules:)]` closure, its `$request` | the field's GraphQL argument names, as sent      |
| the hydrator, and a `#[Arg(type: 'XInput')] array` parameter | PHP property names (`name`), after Rebing's aliasing |
| an `InputRuleProvider`                      | PHP property names                                    |

**Authorization.** `#[Authorize('ability')]` on a model property checks the record it binds before validation, exactly
as on a [model-bound parameter](graphql-authorization.md#authorizing-a-bound-model), at every depth and list index. On
a class that is also a `#[Type]`, an `#[Authorize]` on any other property guards the output field and has no effect on
input.

**Emitted only when used.** An `#[Input]` is registered only when an argument uses it, directly or as a field of
another used input; an unused one, and an enum only it refers to, stay out of the schema. A hand-written Rebing type
cannot reference a discovered input by name unless a discovered argument also uses it. Output types are always
registered.

Discovery throws a `LogicException` for shapes that cannot work: an input property typed as an interface, a union
(other than with [`Omitted`](#partial-updates-with-omitted)), an output-only `#[Type]` or a list of models; an
`#[Input]` class returned from a query or used as a `#[Type]` field unless the class is a `#[Type]` too; `#[Input]` on
an enum, an abstract class or a Rebing type; a `#[Field]` method on an input; `#[Field(rules:)]` outside an input; an
input argument on a `#[Field]` method, also when named by `#[Arg(type:)]` or a type mapper, since field args are neither
validated, hydrated nor authorized; and `#[Authorize]` on an input property that binds no model, without an ability,
with `gate:`, or with `onDenied:`. `#[Input]` on an Eloquent model is rejected as well, since a model in input position
is always an `ID` binding. So is a constructor the hydrator could not call: a required parameter that no input field
fills, or a required one whose field is optional.

### Flattening an input with `#[AsArgs]`

`#[AsArgs]` on an `#[Input]` parameter turns the class's fields into the field's own top-level arguments, and the
resolver still receives the hydrated object. Several `#[AsArgs]` parameters and plain arguments can be mixed.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Validation\Rule\Between;

#[Input]
final readonly class BookSearch
{
    public function __construct(public ?string $term = null, public ?Genre $genre = null, #[Between(1450, 2100)] public ?int $year = null) {}
}

final class BookQueries
{
    #[Query(of: Book::class)] public function findBooks(#[AsArgs] BookSearch $search): array {}
}
```

```graphql
type Query {
  findBooks(term: String, genre: Genre, year: Int): [Book!]!
}
# BookSearchInput is not emitted: nothing uses it as an input object.
```

Each field becomes an argument exactly as it would be an input field: a renamed field keeps its `#[Field(name:)]`, its
description and deprecation, and a scalar, enum or list default. A nested `#[Input]` property stays an input-object
argument, and a model property is an `ID` argument with the automatic `exists` rule, its `#[Authorize('ability')]`
checked before validation. Validation reports at the top-level argument name (`year`, `shipTo.city`), since the
fields are the field's own arguments; laravel-validation attributes, their custom messages and `#[Field(rules:)]` all
apply, and a `#[Field(rules:)]` closure receives the values of that input's own arguments, keyed by PHP property
name. Flattened arguments are named by the [argument naming strategy](#naming), not by the field strategy or
`#[Input(naming:)]`; an explicit `#[Field(name:)]` still wins. Hydration builds the class
from only its own arguments, with the same defaults as a nested input, including an explicit `null` for a property
that takes none keeping its default.

A class used only through `#[AsArgs]` is not registered as an input type; one also used as an input argument
elsewhere is. Discovery rejects `#[AsArgs]` on a parameter that is not an `#[Input]` class, together with `#[Arg]` or
`#[Authorize]` (put `#[Authorize('ability')]` on the input's model property instead), on a nullable parameter or one with a default (flattened arguments have no way to say the whole input is absent; take it
as a nullable input argument instead), and on a `#[Field]` method. A flattened argument whose name another argument,
model binding, arg provider such as `#[Paginated]`, or other `#[AsArgs]` field already takes is rejected as well,
naming both owners; rename one with `#[Field(name:)]` or `#[Arg(name:)]`.

### Partial updates with `Omitted`

GraphQL tells a field the caller left out from one sent as `null`, and a partial update needs both: "leave the
subtitle alone" is not "clear the subtitle". Add the `Omitted` enum to an input property's type, with
`Omitted::Value` as its default, and the property receives `Omitted::Value` when the field is absent.

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

#[Input]
final readonly class UpdateBook
{
    public function __construct(
        public string|Omitted $title = Omitted::Value,          // absent: Omitted, null: rejected
        public string|Omitted|null $subtitle = Omitted::Value,  // absent: Omitted, null: null
    ) {}
}

final class BookMutations
{
    #[Mutation] public function updateBook(#[Arg('id')] Book $book, UpdateBook $input): Book
    {
        if ($input->title !== Omitted::Value) {
            $book->title = $input->title;
        }

        // ...
    }
}
```

```graphql
input UpdateBookInput {
  title: String
  subtitle: String
}
```

In the schema, `Omitted` is removed from the type and the field is optional, with no default value, so an absent
field stays absent. The remaining type is inferred as for any input field: a scalar, an enum, a nested `#[Input]`, a
list through `of:`, an Eloquent model, or whatever a [type mapper](#type-mappers) makes of it. GraphQL accepts `null`
for every optional field, so when `null` is not part of the PHP type, an explicit `null` fails validation with
"The input.title field may be left out, but not set to null.". When `null` is part of it, the property receives `null`.

An absent field runs none of its rules: no laravel-validation attribute, no `#[Field(rules:)]`, not even an implicit
rule such as `required`. A given field runs all of them. A rejected `null` reports only that error.

A model property (`Publisher|Omitted`) is an optional `ID`. Left out, it binds nothing: no `exists` rule and no
`#[Authorize]` check. Given, it is looked up and authorized before validation like any other model property; a
missing record is denied or fails `exists`. `Publisher|Omitted|null` follows the nullable rules instead: an explicit
`null`, or an ID without a record, binds `null`.

`Omitted` works the same in nested inputs, in list items and in [`#[AsArgs]`](#flattening-an-input-with-asargs)
flattened arguments, where a left-out argument hydrates to `Omitted::Value`.

Discovery rejects `Omitted` where it has no meaning: on a `#[Type]` property, a `#[Field]` method or a query return
type; on any property of a class that is both `#[Type]` and `#[Input]` (declare the partial update as its own
`#[Input]`); on a query or mutation parameter (put the optional arguments on an `#[Input]` and flatten it with
`#[AsArgs]`); next to more than one other type (`string|int|Omitted`) or on its own (`?Omitted`); and on a property
whose default is not `Omitted::Value`.

## Arguments

Every parameter becomes a GraphQL argument unless it is one of the injections described below. A scalar or enum
parameter needs no attribute; its GraphQL type comes from the PHP type, and the argument is nullable when the parameter
is nullable or has a default value. An enum parameter becomes an argument of that [enum](#enums) and receives the case.
A [type mapper](#type-mappers) can give an argument a different type, and it can type a class parameter that carries
`#[Arg]`, such as a date.
An [`#[Input]`](#input-types) parameter becomes an argument of that input type and receives the hydrated object;
with [`#[AsArgs]`](#flattening-an-input-with-asargs) its fields become arguments of their own instead.

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
| `type`              | `?string`                       | The GraphQL type. Required for a class no type mapper handles; rejected on an `#[Input]` parameter. |
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

## Naming

By default every name in the schema is the PHP name: a property `$publishedAt` is the field `publishedAt`, a parameter
`$maxWidth` the argument `maxWidth`, and a method `latestBooks()` the query `latestBooks`. An app that uses snake_case
in its API sets a naming strategy per kind of name, under `naming` in the [configuration](#configuration):

```php
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;

// config/discovery-graphql.php
return [
    'naming' => [
        'fields' => FieldCase::Snake,          // fields of a #[Type] or an #[Input]
        'arguments' => FieldCase::Snake,       // #[Query]/#[Mutation] args, #[Field] method args, model bindings, #[AsArgs]
        'operations' => FieldCase::Preserve,   // #[Query] and #[Mutation] field names
    ],
];
```

```php
#[Type]
final class Book
{
    public function __construct(
        public string $title,
        public ?CarbonImmutable $publishedAt,          // DateTime through the scalar map
        #[Field(name: 'ISBN')] public string $isbnCode,
    ) {}

    #[Field]
    public function coverUrl(int $maxWidth = 100): string { /* ... */ }
}

#[Input]
final readonly class ShelveBook
{
    public function __construct(public string $shelfLabel, public ?int $rowNumber = null) {}
}

class Books
{
    #[Mutation]
    public function shelveBook(ShelveBook $shelveRequest, User $bookOwner): Book { /* ... */ }
}
```

```graphql
type Book {
  title: String!
  published_at: DateTime
  ISBN: String!
  cover_url(max_width: Int = 100): String!
}

input ShelveBookInput {
  shelf_label: String!
  row_number: Int
}

type Mutation {
  shelveBook(shelve_request: ShelveBookInput!, book_owner: ID!): Book!
}
```

Each setting takes a `FieldCase` case, its string value (`'preserve'`, `'camel'`, `'snake'`), or the class-string of
your own `NamingStrategy`, whose `name(string $phpName): string` turns a PHP name into a GraphQL name; it is resolved
from the container. `Camel` and `Snake` use Laravel's `Str::camel()` and `Str::snake()`. A setting you leave out is
`Preserve`.

- **An explicit name wins.** `#[Field(name:)]`, `#[Arg(name:)]`, `#[Query(name:)]` and `#[Mutation(name:)]` are used as
  written, whatever the strategy.
- **Type names and enum values never change.** `#[Type]`, `#[Input]` and `#[Enum]` names, and the values of an enum,
  which stay its case names.
- **One type can differ.** `#[Type(naming: FieldCase::Preserve)]` names that type's fields and the args of its
  `#[Field]` methods with its own strategy, and `#[Input(naming: ...)]` does the same for an input's fields. A
  `#[Query]` or `#[Mutation]` on a `#[Type]` class still follows the configured settings.
- **Some args keep their names.** The args an attribute such as `#[Paginated]` or `#[Sortable]` adds, and everything a
  hand-written Rebing class declares.
- **PHP keeps its own names.** A field reads its property or calls its method, and every argument reaches the parameter
  it came from. A renamed input field carries Rebing's `alias`, so the hydrator and an `#[Arg(type: 'XInput')] array`
  parameter receive property names. `#[Relation]` loads the relation the PHP method names.
- **Validation speaks GraphQL.** Errors, and the custom `message:` of a validation attribute, are reported at the
  GraphQL path: `shelve_request.shelf_label`, `author_name`. A `#[Field(rules:)]` closure still receives the input's
  values under their PHP property names.
- **`#[AsArgs]` fields are arguments.** The fields an [`#[AsArgs]`](#flattening-an-input-with-asargs) parameter
  flattens follow the `arguments` setting, not `fields` or the input's own `#[Input(naming:)]`.
- **Acronyms split.** `Str::snake('userID')` is `user_i_d` and `Str::camel('URL')` is `uRL`. Give such a member its
  name with `#[Field(name:)]` or `#[Arg(name:)]`.

Discovery throws a `LogicException` for a naming setting it cannot use: a key other than `fields`, `arguments` and
`operations`, a value that is no `FieldCase` or `NamingStrategy`, a strategy class the container resolves to something
else, and a strategy that returns something that is not a valid GraphQL name. Two owners of one argument name (two
parameters, a model binding, an `#[AsArgs]` field or an argument `#[Paginated]` adds) are rejected with both named, and
so are two queries or two mutations that end up with one name in one schema; rename one with `#[Query(name:)]` or
`#[Mutation(name:)]`.

Names are decided at discovery and cached with it, so run `php artisan discovery:clear` (or `php artisan optimize`)
after changing a strategy.

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
Field, argument and operation names are part of the cached items, so a changed [naming strategy](#naming) only shows
once discovery is cleared. Rebuild both when you change a field or a strategy:

```bash
php artisan optimize
```

## Where to next

- [GraphQL authorization](graphql-authorization.md): `#[Authorize]`, gates, authorizing a bound model, and field
  authorization.
- [GraphQL argument validation and hydration](graphql-arguments.md): validating arguments and hydrating value objects,
  and the hooks for wiring in your own library.
- [Validation](validation.md): the attribute-based rules that back argument validation.
