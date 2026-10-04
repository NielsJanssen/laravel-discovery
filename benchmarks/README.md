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

The suite takes about seven and a half minutes on the machine below.

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
| `small`  | 10    | 16           | 12     | 11    | 16      | 11        | 136                             |
| `medium` | 50    | 56           | 52     | 51    | 56      | 51        | 536                             |
| `large`  | 200   | 206          | 202    | 201   | 206     | 201       | 2036                            |

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
| `benchNestedList`       | `volumes(count: 100)`: 100 objects, each with a nested object, a list of 3 objects, an enum and a string list |
| `benchValidatedArgs`    | `search(term:, limit:)`: two arguments with validation rules                                                  |
| `benchInputMutation`    | `createVolume(input:)`: a nested input object with field rules, hydrated into objects by discovery            |
| `benchEnum`             | `genres(after:, count: 20)`: an enum argument and a list of 20 enum values                                    |
| `benchAuthorizedFields` | `accounts(count: 100)`: an operation-level check (`#[Authorize]` against Rebing's `authorize()`) and a field-level gate on 100 objects, half denied (`#[Authorize('viewBalance')]` against a Rebing `privacy` closure) |
| `benchBatchLoading`     | `authors { books }` over 50 authors with 4 books each: `#[Relation]` against Rebing's lazy relation, and `rebing-eager` |

## Fairness guard

`benchmarks/bin/guard.php` runs before PHPBench and fails the run when the setups are not equivalent. For every size it
boots every setup and checks that:

- the Rebing setups do not load `DiscoveryServiceProvider`, the discovery setups do, and none loads a workbench provider;
- discovery scans exactly the generated classes of its own size;
- the cached setup has the cache strategy on and a cache entry for every location, and has not autoloaded the
  generated classes after boot (proof that it did not scan them); the uncached setup has caching off;
- every setup prints the same SDL (`SchemaPrinter`, types sorted; the fields of `Query` and `Mutation` sorted, since
  discovery registers operations in file system order);
- every setup returns byte-identical JSON for every benchmarked operation and for the generated `item`/`createItem`
  operations of the first and last unit, and none of them errors;
- and it reports the number of SQL queries of the batch loading operation per setup.

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

Milliseconds. The percentage is the difference to `rebing`. A **bold** rstdev is above 5%: read
that mode as approximate. The guard passed for all three sizes before the run.

### Cold boot per request

| Size   | rebing mode / mean / rstdev      | uncached mode / mean / rstdev  | cached mode / mean / rstdev    | uncached vs rebing | cached vs rebing |
|--------|----------------------------------|--------------------------------|--------------------------------|--------------------|------------------|
| small  | 30.08 / 32.32 / **±10.95%**      | 65.63 / 66.53 / ±3.42%         | 38.83 / 39.28 / ±3.95%         | +118%              | +29%             |
| medium | 40.25 / 43.16 / **±11.44%**      | 85.90 / 93.07 / **±9.23%**     | 45.36 / 48.04 / **±9.00%**     | +113%              | +13%             |
| large  | 73.39 / 74.49 / ±3.82%           | 157.41 / 158.15 / ±1.62%       | 61.81 / 62.14 / ±2.04%         | +114%              | −16%             |

### First schema build in a warm process

| Size   | Subject               | rebing mode / mean / rstdev  | uncached mode / mean / rstdev | cached mode / mean / rstdev  | uncached vs rebing | cached vs rebing |
|--------|-----------------------|------------------------------|-------------------------------|------------------------------|--------------------|------------------|
| small  | `benchSchema`         | 4.69 / 4.77 / **±9.29%**     | 1.55 / 1.58 / **±6.91%**      | 2.56 / 2.62 / ±4.63%         | −67%               | −45%             |
| small  | `benchSchemaAllTypes` | 5.82 / 5.87 / ±3.15%         | 2.31 / 2.33 / ±4.39%          | 3.92 / 3.95 / ±4.27%         | −60%               | −33%             |
| small  | `benchFirstQuery`     | 9.33 / 9.46 / ±4.53%         | 5.56 / 5.61 / ±4.07%          | 7.63 / 7.74 / ±4.70%         | −40%               | −18%             |
| medium | `benchSchema`         | 11.93 / 12.26 / **±19.68%**  | 2.01 / 2.05 / ±3.51%          | 3.05 / 3.07 / ±2.44%         | −83%               | −74%             |
| medium | `benchSchemaAllTypes` | 15.49 / 15.65 / ±4.80%       | 4.06 / 4.12 / ±3.00%          | 7.29 / 7.41 / ±4.28%         | −74%               | −53%             |
| medium | `benchFirstQuery`     | 18.35 / 18.59 / ±4.00%       | 6.23 / 6.33 / ±4.75%          | 9.80 / 9.90 / ±2.71%         | −66%               | −47%             |
| large  | `benchSchema`         | 40.00 / 41.54 / **±20.93%**  | 4.11 / 4.09 / ±2.91%          | 5.00 / 5.07 / ±4.08%         | −90%               | −88%             |
| large  | `benchSchemaAllTypes` | 52.87 / 53.59 / **±6.42%**   | 11.20 / 11.29 / ±2.01%        | 20.41 / 20.58 / ±1.77%       | −79%               | −61%             |
| large  | `benchFirstQuery`     | 53.95 / 55.02 / **±5.03%**   | 9.22 / 9.28 / ±2.95%          | 18.93 / 19.53 / **±11.74%**  | −83%               | −65%             |

### Warm execution

Every warm result has an rstdev below 5%, most below 2%; the mean is within 1.3% of the mode everywhere. Peak memory of
the measured process is in the `variants` report; at the large size it is about 12–13 MB for discovery against 7–8.5 MB
for Rebing, since discovery's items, the type registry and the bound fields stay in memory.

| Size  | Subject                 | rebing mode / mean | uncached mode / mean | cached mode / mean | rebing-eager mode / mean | uncached vs rebing | cached vs rebing |
|-------|-------------------------|--------------------|----------------------|--------------------|--------------------------|--------------------|------------------|
| small | `benchScalar`           | 0.126 / 0.126      | 0.146 / 0.146        | 0.146 / 0.146      |                          | +16%               | +16%             |
| small | `benchNestedList`       | 3.594 / 3.604      | 3.466 / 3.473        | 3.470 / 3.477      |                          | −4%                | −3%              |
| small | `benchValidatedArgs`    | 0.199 / 0.200      | 0.232 / 0.230        | 0.231 / 0.231      |                          | +16%               | +16%             |
| small | `benchInputMutation`    | 0.610 / 0.613      | 0.675 / 0.678        | 0.671 / 0.679      |                          | +11%               | +10%             |
| small | `benchEnum`             | 0.167 / 0.168      | 0.192 / 0.192        | 0.192 / 0.192      |                          | +15%               | +15%             |
| small | `benchAuthorizedFields` | 1.610 / 1.613      | 1.649 / 1.653        | 1.642 / 1.655      |                          | +2%                | +2%              |
| small | `benchBatchLoading`     | 5.578 / 5.593      | 3.657 / 3.662        | 3.631 / 3.667      | 3.767 / 3.795            | −34%               | −35%             |
| large | `benchScalar`           | 0.129 / 0.129      | 0.148 / 0.148        | 0.148 / 0.148      |                          | +15%               | +15%             |
| large | `benchNestedList`       | 3.633 / 3.635      | 3.508 / 3.536        | 3.510 / 3.525      |                          | −3%                | −3%              |
| large | `benchValidatedArgs`    | 0.202 / 0.202      | 0.233 / 0.234        | 0.234 / 0.235      |                          | +15%               | +16%             |
| large | `benchInputMutation`    | 0.615 / 0.619      | 0.678 / 0.678        | 0.675 / 0.678      |                          | +10%               | +10%             |
| large | `benchEnum`             | 0.169 / 0.169      | 0.194 / 0.196        | 0.195 / 0.195      |                          | +15%               | +15%             |
| large | `benchAuthorizedFields` | 1.635 / 1.635      | 1.661 / 1.661        | 1.664 / 1.661      |                          | +2%                | +2%              |
| large | `benchBatchLoading`     | 5.625 / 5.644      | 3.647 / 3.662        | 3.633 / 3.646      | 3.772 / 3.794            | −35%               | −35%             |
SQL queries of the batch loading operation, from the guard, at every size: `rebing` 51, `rebing-eager` 2, `uncached` 2,
`cached` 2.

### Reading the results

- **Uncached discovery doubles a cold request.** It adds about 35 ms at the small size: scanning `tempest/*` and the
  three packages is a fixed cost, before any class of the application. It then grows by about 0.45 ms per unit, against
  0.23 ms for Rebing, so it stays at roughly twice Rebing's time. Discovery without its cache does not belong behind
  PHP-FPM in production.
- **The cache buys 41 to 61% of a cold request** (66 → 39 ms small, 86 → 45 medium, 157 → 62 large). It grows only
  about 0.12 ms per unit, so cached discovery costs 9 ms more than Rebing at 10 units, 5 ms more at 50, and is 12 ms
  faster at 200.
- **Rebing pays for its classes when it builds the schema.** Rebing's first `schema()` loads every query, mutation and
  type class: 876 classes at the large size, about 820 of them generated. With those classes loaded beforehand, the same `schema()` takes 6 ms rather
  than 40 ms, which matches discovery. Discovery builds fields from its cached descriptions and never loads an action
  class until that action resolves. Uncached discovery has also already loaded the classes while scanning. The schema
  build gap is therefore mostly class loading, which is why cold boot is the number to compare. Opcache shared memory
  under PHP-FPM loads classes faster than this file cache does, so expect that gap to be smaller in production.
- **In a warm process, discovery costs a small fixed amount per operation and does not depend on schema size.** That is
  about 0.02 ms (+15%) on a trivial query, about 0.03 ms on validated args and enums, about 0.06 ms (+10%) on an input
  mutation that it validates and hydrates into objects, and about 2% on 100 field-level authorization checks. On 100
  nested objects discovery is 3% faster than Rebing's default resolvers. Batch loading saves 35% of the time and 49 of
  51 queries compared with a naive Rebing resolver, and is 3.5% faster than a hand-written `with('books')`.

### What these numbers do not cover

- HTTP: Rebing's controller, routing and middleware are left out, so they are the same for every setup and are not
  measured. A real request adds them to every row.
- PHP-FPM itself: the opcache file cache stands in for opcache shared memory, and PHPBench turns off the garbage
  collector in the measured process.
- Noise: macOS spreads single-shot processes over performance and efficiency cores, which is the likely source of the
  bold rstdev values. The cold boot and schema build rows use 100 and 60 iterations and report the mode, which holds
  up under that bimodal spread. The warm rows, which repeat the operation inside one process, stay below 2% rstdev in
  most cases.
- A cached configuration (`config:cache`): every setup runs without one. Validation failures, error formatting and
  schemas other than the default one are not benchmarked either.
- The `rebing-eager` column of the `setups` report reads `0.000ms` for every subject except batch loading, where it was
  not run.
