# Benchmarks

PHPBench benchmarks that compare `nielsjanssen/laravel-discovery-graphql` with plain
[Rebing GraphQL](https://github.com/rebing/graphql-laravel), on the same schema, the same queries and the same data.

## Running

```bash
composer bench
```

`composer bench` runs `benchmarks/bin/bench.php`, which:

1. generates the schema classes for every setup and size into `benchmarks/.generated/` (gitignored);
2. builds the discovery cache for the cached setup with `discovery:cache`;
3. runs the fairness guard, and stops when it fails;
4. runs PHPBench with the `setups` and `variants` reports.

Arguments after `--` replace the default report options and go to `phpbench run`:

```bash
composer bench -- --report=setups --filter=Warm
composer bench -- --report=setups benchmarks/Bench/ColdBootLargeBench.php
composer bench -- --report=setups --dump-file=results.xml
```

The steps also run on their own: `php benchmarks/bin/generate.php`, `php benchmarks/bin/prepare.php` and
`php benchmarks/bin/guard.php`. Run `vendor/bin/phpbench` through `bench.php` rather than directly, since `bench.php`
passes the absolute opcache file cache path that `phpbench.json` cannot hold.

The suite takes about 13 minutes on the machine below.

### On GitHub Actions

The **Benchmark** workflow (`.github/workflows/benchmark.yml`) runs the suite on demand: start it from the Actions tab
on any branch, optionally with a PHPBench filter such as `Warm` or `ColdBoot`. The job summary shows the reports with
the runner's CPU and PHP version, and the run keeps `benchmark.txt` and the XML dump `benchmark.xml` as an artifact.
Shared runners are noisier than a quiet local machine, so compare runs on the same runner type and read the mode.

## The three setups

| Setup      | What boots                                                                                                    |
|------------|---------------------------------------------------------------------------------------------------------------|
| `rebing`   | Rebing's provider only. Hand-written `Type`, `InputType`, `EnumType`, `Query` and `Mutation` classes, registered in `graphql.types` and `graphql.schemas.default`. |
| `uncached` | The discovery, validation and GraphQL discovery providers plus Rebing's. The same schema as attributes (`#[Type]`, `#[Input]`, PHP enums, `#[Query]`, `#[Mutation]`, `#[Arg(rules:)]`, `#[Field(rules:)]`, `#[Authorize]`, `#[Relation]`). Discovery scans on every boot. |
| `cached`   | As `uncached`, with the discovery cache (`files` store) built beforehand by `discovery:cache`, and the cache strategy on. |

`rebing-eager` is a fourth variant for the batch loading benchmark only: the Rebing setup with `->with('books')` in the
`authors` resolver, which is what a Rebing user writes against the N+1 problem.

Everything else is the same for every setup: a bare Testbench skeleton (no workbench providers, package discovery off),
the configuration not cached, `scoped_schemas` at its default (`true`), SQLite in memory, the same authenticated user
and the same gate. Each setup and size has its own storage directory.

Discovery scans what a consumer application scans: the `tempest/*` packages, the three packages of this repository, and
the generated discovery classes of the size under test. Each size has its own base path
(`benchmarks/.generated/<Size>/composer.json`, with a `vendor` link to the repository's), and `discovery.autoload` points
at it, so sizes and setups never see each other's classes. The Rebing classes of a size are not in that `composer.json`,
so discovery never registers them a second time.

## Schema sizes

Every size has the same feature operations below, plus a number of generated units. A unit is one object type, one input
type, one enum, one query and one mutation (`item7`, `createItem7`).

| Size     | Units | Object types | Inputs | Enums | Queries | Mutations | Generated classes (both setups) |
|----------|-------|--------------|--------|-------|---------|-----------|---------------------------------|
| `small`  | 10    | 18           | 12     | 11    | 22      | 12        | 158                             |
| `medium` | 50    | 58           | 52     | 51    | 62      | 52        | 558                             |
| `large`  | 200   | 208          | 202    | 201   | 212     | 202       | 2058                            |

## What is measured

All times are PHPBench's mode per iteration, in milliseconds. Every iteration runs in a fresh PHP process (PHPBench's
default executor), with the warmup and revolutions inside that process.

**Cold boot** (`ColdBoot*Bench::benchBootAndQuery`): create and boot the application, run discovery or read its cache,
build the schema and execute `{ greet(name: "Acme") }`. One revolution, no warmup, 100 iterations, so each measurement
is one fresh process: the PHP-FPM cost of a request, without HTTP.

**First schema build** (`SchemaBuild*Bench`), measured after a boot that is not timed, 60 iterations of one revolution:

- `benchSchema`: the first `GraphQL::schema()`;
- `benchSchemaAllTypes`: the first `GraphQL::schema()->getTypeMap()`, which builds every type, as introspection does;
- `benchFirstQuery`: the first `{ greet }` query, schema build included.

**Warm execution** (`Warm*Bench`): one booted application, three warmup revolutions (which build the schema), then 20
iterations of 10 to 200 revolutions, the cost per request in Octane or a worker:

| Subject                 | Operation                                                                                                     |
|-------------------------|---------------------------------------------------------------------------------------------------------------|
| `benchScalar`           | `greet(name:)`: a scalar query                                                                                |
| `benchValidatedArgs`    | `search(term:, limit:)`: two arguments with validation rules                                                  |
| `benchInputMutation`    | `createVolume(input:)`: a nested input object with field rules, hydrated into objects by discovery            |
| `benchEnum`             | `genres(after:, count: 20)`: an enum argument and a list of 20 enum values                                    |
| `benchAuthorizedFields` | `accounts(count: 100)`: an operation-level check (`#[Authorize]` against Rebing's `authorize()`) and a field-level gate on 100 objects, half denied (`#[Authorize('viewBalance')]` against a Rebing `privacy` closure) |
| `benchBatchLoading`     | `authors { books }` over 50 authors with 4 books each: `#[Relation]` against Rebing's lazy relation, and `rebing-eager` |

**Scenarios** (`Scenario*Bench`), warm like the above, each against the same behaviour hand-written in Rebing:

| Subject                    | Discovery                                                                     | Plain Rebing                                                             |
|----------------------------|-------------------------------------------------------------------------------|--------------------------------------------------------------------------|
| `benchModelBinding`        | `author(#[Arg('id')] Author $author)`: an `ID` bound to the model, with the automatic `exists` rule | `'rules' => ['exists:bench_authors,id']` and a `where()->firstOrFail()` in the resolver |
| `benchInvalidArgs`         | `renameVolume(title:, edition:)` with `#[Arg(rules:)]`, sent with two invalid values (the error path) | the same rules in `args()`                                  |
| `benchInvalidInput`        | `createVolume(input:)` sent with four invalid fields, nested ones included     | the same rules on the `InputType` fields                                 |
| `benchPaginated`           | `#[Paginated]` with `Pagination` on a query builder, page 2 of 25             | `GraphQL::paginate('Book')` and `->paginate()` in the resolver           |
| `benchMiddleware`          | `#[Middleware(ShoutMiddleware::class)]` on the action                         | `protected $middleware = [ShoutMiddleware::class]` on the query          |
| `benchAuthorizationHelper` | an `Authorization` parameter and `$authorization->authorize('openVault', $number)` | `Gate::denies()` and a thrown `AuthorizationError` in the resolver   |
| `benchFactoryFields`       | 100 objects whose four `Int` fields come from a `TypeFactory`                 | the same four fields with `resolve` closures in `fields()`               |
| `benchProvidedType`        | 100 rows of a type from a `TypeProvider`                                       | a hand-written `Type` with the same fields                               |

**Payload size** (`Payload*Bench::benchNestedList`): `volumes(count:)` at 10, 100 and 1000 items, each with a nested
object, a list of 3 objects, an enum and a string list. The item count is a PHPBench parameter.

**HTTP** (`Http*Bench`): `POST /graphql` with a JSON body, dispatched through the HTTP kernel, its global middleware
stack (`ValidatePathEncoding`, `TrustProxies`, `HandleCors`, `ValidatePostSize`, `TrimStrings`, …), routing and Rebing's
controller, then terminated. `benchHttpScalar` sends the scalar query and `benchHttpInputMutation` the input mutation.
Every setup uses Rebing's own route and the same Testbench kernel; no network listener is involved.

## Fairness guard

`benchmarks/bin/guard.php` runs before PHPBench and fails the run when the setups are not equivalent. For every size it
boots every setup and checks that:

- the Rebing setups do not load `DiscoveryServiceProvider`, the discovery setups do, and none loads a workbench provider;
- discovery scans exactly the generated classes of its own size;
- the cached setup has the cache strategy on and a cache entry for every location, and has not autoloaded the
  generated classes after boot (proof that it did not scan them); the uncached setup has caching off;
- every setup prints the same SDL (`SchemaPrinter`, types sorted; the fields of `Query` and `Mutation` sorted, since
  discovery registers operations in file system order);
- every setup returns byte-identical JSON for every benchmarked operation (the payload at every item count included)
  and for the generated `item`/`createItem` operations of the first and last unit; every one of them succeeds, except
  the two invalid mutations, which must fail with the `validation` category and whose full error responses are compared;
- both HTTP requests return the same status and the same body in every setup;
- and it reports the number of SQL queries of batch loading, model binding and pagination per setup.

## Environment

- Each iteration runs with `opcache.enable_cli=1` and `opcache.file_cache` set to `benchmarks/.generated/opcache`, so a
  fresh process reads compiled scripts from the file cache, the way a PHP-FPM worker reads them from shared memory.
  The guard runs with the same settings first, so the file cache is warm before measuring. Without it, the cold boot
  would mostly measure compiling Laravel. JIT is off and `memory_limit` is `-1`.
- PHPBench disables the garbage collector inside each measured process.
- Pest only runs `tests/`, and discovery in the test suite reads `autoload.psr-4` only, never `autoload-dev`, so the
  benchmark classes and the generated directory stay out of the test suite. Pint excludes `benchmarks/.generated`, and
  PHPStan analyses `benchmarks/` without it.

## Results

Run of 4 October 2026 on an Apple M5 Pro (18 cores, 48 GB), macOS 27.0.1, PHP 8.5.10 NTS (Laravel Herd) with opcache
file cache and JIT off; Laravel 13.34.0, Testbench Core 11.5.0, rebing/graphql-laravel 10.0.0, webonyx/graphql-php
15.37.3, PHPBench 1.7.0.

Milliseconds, as mode / mean / rstdev. The percentage is the difference in mode to `rebing`. A **bold** rstdev is above
5%: read that mode as approximate. The guard passed for all three sizes before the run.

### Cold boot per request

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchBootAndQuery` | 31.09 / 34.04 / **±13.70%** | 68.33 / 73.97 / **±9.81%** | 41.15 / 44.45 / **±10.33%** |  | +120% | +32% |
| medium | `benchBootAndQuery` | 40.55 / 41.42 / **±9.86%** | 87.76 / 94.10 / **±9.03%** | 47.20 / 50.96 / **±9.72%** |  | +116% | +16% |
| large | `benchBootAndQuery` | 72.43 / 72.98 / ±3.20% | 156.75 / 158.27 / ±2.69% | 61.45 / 61.57 / ±2.44% |  | +116% | −15% |

### First schema build in a warm process

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchSchema` | 5.507 / 6.080 / **±15.15%** | 1.930 / 2.068 / **±23.22%** | 3.196 / 3.495 / **±14.93%** |  | −65% | −42% |
| small | `benchSchemaAllTypes` | 6.489 / 6.989 / **±14.38%** | 2.789 / 2.960 / **±11.88%** | 5.184 / 8.160 / **±61.73%** |  | −57% | −20% |
| small | `benchFirstQuery` | 10.287 / 11.266 / **±21.02%** | 6.041 / 6.729 / **±14.01%** | 8.250 / 8.534 / **±8.85%** |  | −41% | −20% |
| medium | `benchSchema` | 12.156 / 12.617 / **±23.10%** | 2.284 / 2.312 / ±4.09% | 3.610 / 3.624 / ±3.97% |  | −81% | −70% |
| medium | `benchSchemaAllTypes` | 15.745 / 15.966 / **±6.75%** | 4.330 / 4.401 / **±5.10%** | 8.032 / 8.131 / ±4.33% |  | −72% | −49% |
| medium | `benchFirstQuery` | 18.510 / 18.691 / ±3.53% | 6.319 / 6.408 / ±3.51% | 10.266 / 10.328 / ±3.28% |  | −66% | −45% |
| large | `benchSchema` | 39.352 / 39.725 / ±3.85% | 4.306 / 4.339 / ±4.09% | 5.562 / 5.600 / ±4.14% |  | −89% | −86% |
| large | `benchSchemaAllTypes` | 52.275 / 52.472 / ±2.35% | 11.299 / 11.351 / ±2.55% | 21.254 / 21.638 / ±4.27% |  | −78% | −59% |
| large | `benchFirstQuery` | 54.309 / 56.184 / **±6.38%** | 9.386 / 9.575 / ±3.97% | 18.932 / 19.009 / ±2.80% |  | −83% | −65% |

### Warm execution

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchScalar` | 0.124 / 0.124 / ±1.64% | 0.143 / 0.142 / ±0.64% | 0.143 / 0.143 / ±1.01% |  | +15% | +15% |
| small | `benchValidatedArgs` | 0.193 / 0.193 / ±0.61% | 0.224 / 0.224 / ±0.77% | 0.226 / 0.225 / ±0.60% |  | +16% | +17% |
| small | `benchInputMutation` | 0.596 / 0.597 / ±1.30% | 0.653 / 0.654 / ±0.47% | 0.652 / 0.652 / ±0.56% |  | +10% | +9% |
| small | `benchEnum` | 0.163 / 0.163 / ±0.39% | 0.188 / 0.188 / ±1.17% | 0.187 / 0.187 / ±0.65% |  | +15% | +15% |
| small | `benchAuthorizedFields` | 1.566 / 1.568 / ±0.83% | 1.608 / 1.609 / ±1.31% | 1.605 / 1.610 / ±1.15% |  | +3% | +2% |
| small | `benchBatchLoading` | 5.416 / 5.438 / ±0.74% | 3.511 / 3.530 / ±1.26% | 3.502 / 3.504 / ±0.58% | 3.651 / 3.658 / ±0.83% | −35% | −35% |
| large | `benchScalar` | 0.129 / 0.130 / ±0.54% | 0.149 / 0.149 / ±0.72% | 0.149 / 0.150 / ±1.24% |  | +16% | +16% |
| large | `benchValidatedArgs` | 0.201 / 0.202 / ±0.78% | 0.233 / 0.234 / ±0.92% | 0.233 / 0.233 / ±0.73% |  | +16% | +16% |
| large | `benchInputMutation` | 0.612 / 0.614 / ±0.55% | 0.676 / 0.674 / ±0.61% | 0.673 / 0.674 / ±0.70% |  | +10% | +10% |
| large | `benchEnum` | 0.168 / 0.168 / ±0.45% | 0.192 / 0.192 / ±0.83% | 0.192 / 0.192 / ±0.67% |  | +14% | +14% |
| large | `benchAuthorizedFields` | 1.616 / 1.617 / ±0.84% | 1.643 / 1.648 / ±1.00% | 1.636 / 1.639 / ±0.67% |  | +2% | +1% |
| large | `benchBatchLoading` | 5.527 / 5.550 / ±0.91% | 3.581 / 3.590 / ±0.66% | 3.584 / 3.603 / ±1.55% | 3.727 / 3.733 / ±0.70% | −35% | −35% |

### Scenarios

Warm, like the table above. `benchInvalidArgs` and `benchInvalidInput` time the validation error path.

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchModelBinding` | 0.255 / 0.256 / ±1.56% | 0.301 / 0.302 / ±0.81% | 0.301 / 0.301 / ±0.84% |  | +18% | +18% |
| small | `benchInvalidArgs` | 0.234 / 0.234 / ±1.31% | 0.257 / 0.257 / ±0.68% | 0.258 / 0.257 / ±0.64% |  | +10% | +10% |
| small | `benchInvalidInput` | 0.449 / 0.449 / ±0.70% | 0.492 / 0.491 / ±0.46% | 0.491 / 0.492 / ±1.10% |  | +10% | +9% |
| small | `benchPaginated` | 0.762 / 0.764 / ±1.11% | 0.741 / 0.740 / ±0.60% | 0.740 / 0.741 / ±1.24% |  | −3% | −3% |
| small | `benchMiddleware` | 0.128 / 0.129 / ±1.86% | 0.147 / 0.147 / ±0.57% | 0.148 / 0.147 / ±0.71% |  | +15% | +16% |
| small | `benchAuthorizationHelper` | 0.131 / 0.131 / ±0.83% | 0.152 / 0.153 / ±0.64% | 0.152 / 0.153 / ±0.45% |  | +16% | +16% |
| small | `benchFactoryFields` | 0.988 / 0.988 / ±1.15% | 1.018 / 1.016 / ±0.62% | 1.015 / 1.019 / ±0.60% |  | +3% | +3% |
| small | `benchProvidedType` | 0.709 / 0.712 / ±0.93% | 0.707 / 0.712 / ±1.84% | 0.710 / 0.709 / ±0.82% |  | −0% | +0% |
| large | `benchModelBinding` | 0.276 / 0.275 / ±1.74% | 0.319 / 0.319 / ±1.03% | 0.378 / 0.357 / **±8.41%** |  | +16% | +37% |
| large | `benchInvalidArgs` | 0.248 / 0.247 / ±0.74% | 0.272 / 0.274 / ±2.16% | 0.272 / 0.271 / ±0.63% |  | +10% | +10% |
| large | `benchInvalidInput` | 0.475 / 0.475 / ±0.86% | 0.518 / 0.521 / ±1.66% | 0.524 / 0.524 / ±1.04% |  | +9% | +10% |
| large | `benchPaginated` | 0.809 / 0.811 / ±1.57% | 0.783 / 0.832 / **±9.28%** | 0.786 / 0.789 / ±0.93% |  | −3% | −3% |
| large | `benchMiddleware` | 0.135 / 0.135 / ±0.93% | 0.156 / 0.158 / ±2.69% | 0.156 / 0.156 / ±0.87% |  | +16% | +16% |
| large | `benchAuthorizationHelper` | 0.142 / 0.140 / ±1.38% | 0.161 / 0.162 / ±0.90% | 0.161 / 0.169 / **±6.60%** |  | +13% | +13% |
| large | `benchFactoryFields` | 1.050 / 1.084 / **±6.00%** | 1.073 / 1.077 / ±1.52% | 1.069 / 1.073 / ±0.88% |  | +2% | +2% |
| large | `benchProvidedType` | 0.754 / 0.756 / ±1.43% | 0.747 / 0.752 / ±1.54% | 0.743 / 0.746 / ±1.26% |  | −1% | −1% |

The bold cached `benchModelBinding` row at the large size is an outlier. A rerun of that subject alone gave
0.260 / 0.306 / 0.304 ms (+17% for both discovery setups), in line with the small size.

### Payload size

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchNestedList (10 items)` | 0.710 / 0.717 / ±1.97% | 0.727 / 0.726 / ±1.58% | 0.730 / 0.728 / ±1.23% |  | +2% | +3% |
| small | `benchNestedList (100 items)` | 3.705 / 3.719 / ±1.23% | 3.596 / 3.605 / ±1.25% | 3.599 / 3.615 / ±1.17% |  | −3% | −3% |
| small | `benchNestedList (1000 items)` | 33.499 / 33.557 / ±0.53% | 32.132 / 32.219 / ±0.64% | 32.202 / 32.310 / ±0.68% |  | −4% | −4% |
| large | `benchNestedList (10 items)` | 0.690 / 0.685 / ±1.89% | 0.692 / 0.695 / ±2.40% | 0.686 / 0.690 / ±1.69% |  | +0% | −1% |
| large | `benchNestedList (100 items)` | 3.531 / 3.552 / ±1.49% | 3.413 / 3.425 / ±1.11% | 3.408 / 3.411 / ±0.91% |  | −3% | −3% |
| large | `benchNestedList (1000 items)` | 32.093 / 32.089 / ±0.67% | 30.618 / 30.819 / ±1.93% | 30.625 / 30.626 / ±0.76% |  | −5% | −5% |

### Full HTTP request

| Size | Subject | rebing mode / mean / rstdev | uncached mode / mean / rstdev | cached mode / mean / rstdev | rebing-eager mode / mean / rstdev | uncached vs rebing | cached vs rebing |
|---|---|---|---|---|---|---|---|
| small | `benchHttpScalar` | 0.274 / 0.275 / ±0.84% | 0.298 / 0.299 / ±1.60% | 0.297 / 0.297 / ±0.84% |  | +9% | +8% |
| small | `benchHttpInputMutation` | 0.799 / 0.802 / ±1.24% | 0.860 / 0.864 / ±1.01% | 0.860 / 0.865 / ±1.12% |  | +8% | +8% |
| large | `benchHttpScalar` | 0.258 / 0.260 / ±1.99% | 0.280 / 0.280 / ±0.69% | 0.279 / 0.280 / ±1.75% |  | +9% | +8% |
| large | `benchHttpInputMutation` | 0.749 / 0.750 / ±1.31% | 0.813 / 0.820 / ±2.10% | 0.809 / 0.812 / ±1.11% |  | +9% | +8% |

SQL queries per operation, from the guard, the same at every size:

| Operation     | rebing | rebing-eager | uncached | cached |
|---------------|--------|--------------|----------|--------|
| batch loading | 51     | 2            | 2        | 2      |
| model binding | 2      | 2            | 2        | 2      |
| pagination    | 2      | 2            | 2        | 2      |

### Reading the results

- **Uncached discovery doubles a cold request.** It adds about 37 ms at the small size: scanning `tempest/*` and the
  three packages is a fixed cost, before any class of the application. It then grows by about 0.47 ms per unit, against
  0.22 ms for Rebing, so it stays at roughly twice Rebing's time. Discovery without its cache does not belong behind
  PHP-FPM in production.
- **The cache buys 40 to 61% of a cold request** (68 → 41 ms small, 88 → 47 medium, 157 → 61 large). It grows only
  about 0.11 ms per unit, so cached discovery costs 10 ms more than Rebing at 10 units, 7 ms more at 50, and is 11 ms
  faster at 200.
- **Rebing pays for its classes when it builds the schema.** Rebing's first `schema()` loads every query, mutation and
  type class: 876 classes at the large size, about 820 of them generated. With those classes loaded beforehand, the
  same `schema()` takes 6 ms rather than 40 ms, which matches discovery. Discovery builds fields from its cached
  descriptions and never loads an action class until that action resolves. Uncached discovery has also already loaded
  the classes while scanning. The schema build gap is therefore mostly class loading, which is why cold boot is the
  number to compare. Opcache shared memory under PHP-FPM loads classes faster than this file cache does, so expect that
  gap to be smaller in production.
- **In a warm process, discovery adds a fixed 0.02 to 0.06 ms per operation, whatever the schema size.** On small
  operations that reads as +13 to +18%: a scalar query, enums, validated args, model binding, `#[Middleware]` and the
  `Authorization` helper all pay about 0.02 ms, the same amount, so none of those features costs anything of its own
  on top of the action wrapper. Input hydration adds about 0.06 ms (+10%), and the validation error path about 0.02 to
  0.04 ms (+10%).
- **Where the work grows with the response, discovery breaks even or wins.** Field-level authorization is +2%, type
  factory fields +2 to +3%, provider types and pagination −1 to −3%, and the nested list goes from +2% at 10 items to
  −3% at 100 and −4 to −5% at 1000, so discovery's field resolvers are slightly cheaper per field than Rebing's
  defaults. Batch loading saves 35% of the time and 49 of 51 queries against a naive Rebing resolver, and is 4% faster
  than a hand-written `with('books')`. Model binding and pagination run the same 2 SQL queries as the hand-written
  Rebing code.
- **Through the full HTTP stack the overhead shrinks to +8 to +9%**, about 0.02 ms on a scalar query and 0.06 ms on
  the input mutation, on top of the 0.13 to 0.15 ms that routing, middleware, the controller and the JSON response add.

### What these numbers do not cover

- PHP-FPM itself: the opcache file cache stands in for opcache shared memory, and PHPBench turns off the garbage
  collector in the measured process. The HTTP benchmark dispatches a request through the kernel inside one process,
  without a web server or network listener.
- Noise: macOS spreads single-shot processes over performance and efficiency cores, which is the likely source of the
  bold rstdev values. The cold boot and schema build rows use 100 and 60 iterations and report the mode, which holds
  up under that bimodal spread. The warm rows, which repeat the operation inside one process, stay below 2% rstdev in
  most cases. The four bold ones are outliers that move between runs: a rerun of three of those subjects brought the
  flagged values under 5% but flagged two other cells of the same subjects.
- A cached configuration (`config:cache`): every setup runs without one. Schemas other than the default one are not
  benchmarked.
- The `rebing-eager` column of the `setups` report reads `0.000ms` for every subject except batch loading, where it was
  not run.
