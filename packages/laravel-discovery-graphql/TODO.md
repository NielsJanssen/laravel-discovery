# TODO

Roadmap for fully replacing Rebing's class-based `Query` / `Mutation` / `Type` API with attribute-described methods. Items here are still open; shipped features have been removed.

## Tier 1 — Parity with Rebing's resolver signature

- **`SelectFields` injection** — a `#[SelectFields]` parameter attribute (or auto-detect by Rebing's `Closure $getSelectFields` injector type) that hands resolvers the same callable Rebing already exposes for Eloquent `with()` pre-loading, with the depth / parsing knobs.
- **`$args` raw access** — a `#[Args]` parameter attribute that hands the full args array. Most resolvers will not need this, but it is a cheap escape hatch for forward-compat (args not declared on the method, dynamic resolvers, etc).

## Tier 2 — Type-system features Rebing has, we don't

- **GraphQL-level argument defaults** — `#[Arg(default: …)]` for when the GraphQL default needs to differ from the PHP default (e.g. a sentinel value).
- **Custom validation messages** — `#[Arg(rules: […], messages: […])]` mirroring Rebing's per-field `validationErrorMessages` mechanism.
- **List / NonNull wrappers in type strings** — today `list: true` is the only way to declare a list, and only `[T!]!` is possible. Either:
  - parse type strings (`'[Book!]'`, `'Book!'`), or
  - richer attribute fields (`list: bool, listOf: 'nullable'|'nonNull', wrap: 'nullable'|'nonNull'`).
- **Union return types** — read PHP union return types (`Book|Author`) and require a matching declaration somewhere, e.g. `#[GraphQLUnion('SearchResult', [Book::class, Author::class])]`.
- **Interface return types** — same shape as unions but for GraphQL interfaces; the discoverer resolves implementing types.

## Tier 3 — Replace the remaining class-based modes with attributes

Mode-1 (`class extends Rebing\Type` / `Query` / `Mutation`) still works and is tested, but every new feature widens the gap between the two modes. To retire it:

- **`#[GraphQLType('Book')]`** on plain classes with **`#[Field]`** per public property/method. `#[Field]` mirrors `#[Query]` (`type`, `nullable`, `list`, `description`, `deprecated`); resolver methods can use `#[Root]` for the parent value.
- **`#[GraphQLInputType('BookInput')]`** for input objects — same shape, but only scalar / input fields are allowed. Lets `#[Arg(type: 'BookInput')]` reference a discovered input type.
- **`#[GraphQLEnum]`** on native PHP enums — auto-register backed enums (and pure enums via case names) as GraphQL enums; the type resolver looks them up by name.
- **`#[GraphQLInterface]`** with the discoverer resolving implementing types.
- **Custom scalar types** — `#[GraphQLScalar('DateTime')]` on a class implementing a small `serialize` / `parseValue` / `parseLiteral` contract.

Once these exist, mode-1 can be deprecated and eventually removed.

## Tier 4 — Cross-cutting decorators

- **`#[Authorize]` — richer forms.** Today: bare `#[Authorize]` (must be logged in) and `#[Authorize(gate: SomeGate::class)]` (custom class). Future shorthands:
  - `#[Authorize('view', subject: User::class, idArg: 'userId')]` — fetch via `User::findOrFail($args['userId'])`, call `Gate::check('view', $instance)`.
  - `#[Authorize('view', subject: fn(array $args) => User::findOrFail($args['userId']))]` — closure resolver for non-Eloquent or composite lookups.
  - `#[Authorize('viewAny', subject: User::class)]` — class-string form for `viewAny`-style checks.
  Open call: should this expose `subject:` on the existing `Authorize` attribute (polymorphic `Closure|string|null` field) or live as a sibling attribute like `#[Authorize\Policy]`?
- **`#[Complexity(5)]`** / **`#[Cost]`** — for query-cost analysis. Plug into Rebing's complexity rules if/when we adopt them.
- **`#[Cacheable(ttl: 60)]`** — opt-in response caching keyed by args + (optionally) context.

## Tier 5 — Developer ergonomics & correctness

- **`#[Returns]` / `#[Of]` shorthand** — `#[Query] #[Returns(Book::class, list: true)]` reads nicer than `#[Query(type: 'Book', list: true)]`, and `Returns` could accept a class-string mapped to its `#[GraphQLType]` name (only meaningful once Tier 3 lands).
- **Better discovery errors** — `discoverActionReturnType()` says "no scalar return type" but doesn't mention union / interface / explicit-type options. Once Tier 2/3 land, this message gets a richer pointer.
- **Test assertions / helpers** — `expect($query)->toResolveTo(...)`, `expect($schema)->toRegister('books')->in('default')`. Cuts duplication in feature tests.
- **`config:cache` compatibility** — `GraphQLCacheTest` still has a `->todo` for "works after `config:cache` + `refreshApplication()`". Worth resolving — the post-discover singleton bindings don't survive a re-bootstrapped container.
- **`#[Arg]` non-scalar default branch** — the `default => GraphQL::type($arg->type)` arm in `AsActionField::args()` is uncovered. Cheap to cover once `#[GraphQLInputType]` exists and we have a real registered input type to point at.
- **Static analysis stubs** — `#[Returns(Book::class)]` could ship a phpstan extension that asserts `Book` extends / has `#[GraphQLType]`. Long-tail.
- **`make:graphql` scaffolders** — `make:graphql:query`, `make:graphql:type`, `make:graphql:input`, mirroring `make:discovery`.

## Tier 6 — Nice-to-haves once the core is settled

- **Subscriptions** — Rebing doesn't ship them, but the discovery layer could expose `#[Subscription]` that integrates with a third party (Pusher, `xkojimedia/laravel-subscribable`) if asked.
- **Federation / Apollo `@key` directives** — out of scope until Rebing supports it; worth flagging as an extension point.
- **OpenAPI / JSON-schema export** from discovered actions — sometimes the same actions need a REST shim.
- **`php artisan graphql:list`** — show every `#[Query]` / `#[Mutation]` with file:line, schema, args. Mirrors `route:list`.

---

## Suggested order to attack

1. **Tier 1 remainder** (`SelectFields`, `$args`) — small additions, unblocks Eloquent-pre-loading recipes.
2. **Tier 3** (`#[GraphQLType]` + `#[Field]` + `#[GraphQLEnum]`) — closes the loop on retiring mode-1 and is a prerequisite for several Tier 2 items.
3. **Tier 2** (descriptions are already shipped; argument defaults, validation messages, list/non-null wrappers, unions/interfaces).
4. **Tier 4** richer `#[Authorize]` once the simpler form has been used in anger and we know what shapes recur.
5. The rest as needs arise.
