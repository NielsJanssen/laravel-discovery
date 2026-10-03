# Type discovery: implementation plan

Status: Phases 0–4 are done and merged into `main`, and a simplification audit has landed on top of them (see
[Progress](#progress) and [Simplification audit](#simplification-audit)); Phase 5, Phase 6, WP6.3 and Phase 7 remain.
Written 2 October 2026, against API proposal draft 3. Revised the same day after a review that verified the plan
against the codebase and vendor code (see [Review changes](#review-changes)). Progress last updated 3 October 2026.

This plan implements attribute-based discovery of GraphQL object types, input types, enums, interfaces and unions
for `nielsjanssen/laravel-discovery-graphql`. Classes become types, and queries and mutations infer their types from
the classes in their signatures. The work is split into work packages (WPs) that can be handed to subagents, one WP
per agent. Each WP lists its dependencies, scope, the files it touches, the tests it must add and when it is done.

The API itself is summarised in [API reference](#api-reference) at the end. Every WP refers to it, so an agent needs
nothing but this file and the codebase.

## Contents

- [Progress](#progress)
- [Simplification audit](#simplification-audit)
- [Priorities](#priorities)
- [Dependency graph and parallel tracks](#dependency-graph-and-parallel-tracks)
- [Rules for every work package](#rules-for-every-work-package)
- [Phase 0: Foundations](#phase-0-foundations)
- [Phase 1: Object types and enums](#phase-1-object-types-and-enums)
- [Phase 2: Inputs](#phase-2-inputs)
- [Phase 3: Naming and inference hooks](#phase-3-naming-and-inference-hooks)
- [Phase 4: Eloquent models, authorization, batch loading](#phase-4-eloquent-models-authorization-batch-loading)
- [Phase 5: Dynamic types and extension](#phase-5-dynamic-types-and-extension)
- [Phase 6: Interfaces and unions (low priority)](#phase-6-interfaces-and-unions-low-priority)
- [Phase 6b: Validation adapter contract](#phase-6b-validation-adapter-contract)
- [Phase 7: Documentation and release](#phase-7-documentation-and-release)
- [Risks and things to verify first](#risks-and-things-to-verify-first)
- [Review changes](#review-changes)
- [API reference](#api-reference)

## Progress

Each WP was implemented by a subagent, reviewed with `/bttr:review`, had its findings folded into its commit, and was
rebase-merged into `main` as one commit per WP (Phase 0 as one commit per WP inside PR #8).

| WP | Status | Commit on `main` | PR |
|----|--------|------------------|----|
| WP0.1 Parameter classifier refactor | Done | `bc59fea` | #8 |
| WP0.2 TypeRef and TypeRegistry | Done | `f0c0d1b` | #8 |
| WP0.3 Type registration and schema test helper | Done | `785110f` | #8 |
| WP1.1 `#[Type]`, `#[Field]`, `#[Ignore]` | Done | `5833cfd`, `1b9b668` | #8 |
| WP1.2 Inference in queries and mutations | Done | `08f3955`, `4ea1664` | #10 |
| WP1.3 Enums | Done | `71bfd4a`, `b2bde4c` | #12 |
| WP2.1 `#[Input]` classes and inferred input args | Done | `00b3c62` | #16 |
| WP2.2 `#[AsArgs]` | Done | `3c8a2c5` | #18 |
| WP2.3 `Omitted` | Done | `95f79e3` | #20 |
| WP3.1 Naming strategy | Done | `c047a1d` | #19 |
| WP3.2 TypeMapper and the scalar map | Done | `f0a8387` | #14 |
| WP4.1 FieldDecorator and field `#[Authorize]` | Done | `cb71924` | #11 |
| WP4.2 Eloquent models | Done | `8ea8d08` | #13 |
| WP4.3 Batch loading | Done | `1331636` | #15 |
| Simplification audit ([details](#simplification-audit)) | Done | Commits `fix: Keep the exists rule for closure rules on bindings` to `docs: Document the test helpers and fixture convention` (hashes pending a rewrite) | |
| WP5.1 TypeFactory | To do | | |
| WP5.2 TypeProvider | To do | | |
| WP5.3 `replace: true` | To do | | |
| WP5.4 `#[ExtendType]` | To do | | |
| WP5.5 Schema-scoped types | To do (added during implementation) | | |
| WP6.1 Interfaces | To do (low priority) | | |
| WP6.2 Unions | To do (low priority) | | |
| WP6.3 Validation adapter contract | To do (added during implementation) | | |
| Phase 7 Documentation and release | To do | | |

Extra commits that came out of the work: `4ea1664` (type builder fields non-null, found while investigating
`#[Paginated]` nullability) and `71bfd4a` (`TypeRegistry` as a `#[Singleton]`, found during WP1.3).

### Deviations from this plan

Decisions made during implementation that change what the WP sections and the API reference below say. Where they
conflict, this list wins.

- **WP1.2:** a type builder's type (e.g. `#[Paginated]`) is wrapped non-null unless the action is nullable; actions with
  a builder skip return inference.
- **WP1.3:** enums referenced but not annotated are stored as implicit discovery items (cache-safe bind names), not
  built in `apply()`. A class-string in `#[Arg(type:)]` must be a registered input type, checked at boot.
- **WP4.1:** `FieldDecorator` has only `decorate(FieldBlueprint)`; the optional `FieldDiscoveryVerifier`
  (`verify(string $member, DiscoveredTypeField $field)`) rejects shapes at discovery. `Denied::Error` keeps the field
  nullable. Class-level `#[Authorize]` on a `#[Type]` is allowed when the class hosts actions (it applies to them only).
  `Authorize::allows()` is the single check for actions, parameters and fields; `DiscoveredModelAuthorization` was
  removed (`Authorize::DEFAULT_MESSAGE`). An ability, ability + `gate:`, or a non-`AuthorizationGate` gate on an action
  class/method is rejected at discovery.
- **WP4.2:** members of `Illuminate\` are skipped for every `#[Type]` (also when redeclared); plain public properties
  imported from any trait are skipped, not rejected. Later made configurable (`discovery.graphql.skip_namespaces`,
  default `['Illuminate\\']`, class `Discovery\SkippedMembers`).
- **WP3.2:** config lives in `packages/laravel-discovery-graphql/config/discovery-graphql.php`, merged under
  `discovery.graphql` and publishable (`discovery-graphql-config`). Consumer mappers run before `ScalarMap`. A
  class-typed parameter needs `#[Arg]` to reach mappers. `DiscoveredArg` keeps the mapped `TypeRef`.
- **WP4.3:** `BatchedField` is named `BatchedFieldDecorator`; implementors `use ResolvesThroughBatchLoader` (which
  supplies `decorate()`). `LoadersExecutionMiddleware` is prepended in `booted()`. `KeyLoader` rejects `many: false` on a
  non-key column and a non-public `key:`.
- **WP2.1:** the laravel-validation rejections (`#[Can]`, nested rules) on input properties were removed again; see
  WP6.3. Model-bound `#[Authorize]` on input properties is enforced for every arg whose type contains a discovered
  input. `#[Type]` field-method args may not take an input type in any form. An explicit `null` on a defaulted
  non-nullable property falls back to the default.
- **WP2.2:** nullable or defaulted `#[AsArgs]` parameters are rejected; flattened inputs are validated by the core
  (`RuleProviderRegistry`), not the adapter.
- **WP3.1:** `#[AsArgs]` fields follow the `arguments` strategy. One collision check covers args, model bindings,
  provider args and flattened fields; duplicate operations (schema, kind, name) are rejected at `apply()`.
  `#[Field(rules:)]` closures receive PHP property names.
- **WP2.3:** `Omitted` in output position, on shared `#[Type]`+`#[Input]` classes, on action parameters (use
  `#[AsArgs]`), or with a non-`Omitted::Value` default is rejected. An absent field runs no rules and no
  authorization.
- **Simplification audit:** `GraphQLDiscovery` no longer holds validation, reference walking or return-type
  resolution: see `SchemaValidator`, `TypeUsage`, `ReturnTypeResolver` and `FieldMembers`. `#[Authorize]` shape rules are
  `Authorize::verify()`, `verifyOnAction()`, `verifyOnParameter()` and `verifyOnProperty()`. The WP sections below name
  the new locations; where an older paragraph says `GraphQLDiscovery` does a check, look in those classes.

### Open follow-ups

- Discovery items cached before Phases 1–4 do not unserialize (new readonly DTO fields); the release notes must say to
  run `discovery:clear` / `optimize` after upgrading.
- Nested laravel-validation rules on input properties are not applied, and the parameter-level `#[Can]` check still
  lives in `ParameterClassifier` (WP6.3).
- A Rebing type that only exists in `graphql.types` config is silently overwritten by a discovered type with the same
  name (suggested as a separate task, not yet done).
- `CLAUDE.md`'s GraphQL section and the docs split are part of Phase 7.

## Simplification audit

Done 3 October 2026, after Phases 0–4 and before Phase 5. It looked for code and tests that Phases 0–4 had made larger
than needed, and for seams the remaining WPs would otherwise have to cut themselves. The audit document is not in the
repo; this section keeps what a later WP needs. Where a WP below names a class, it names the one the audit left.

| | Before | After |
|---|---|---|
| `tests/Fixtures/RebingGraphQL` | 375 files, 7749 lines | 213 files, 4985 lines |
| `tests/Feature/RebingGraphQL` | 25 files, 7712 lines, 657 tests | 26 files, 7407 lines, 630 tests |
| `GraphQLDiscovery.php` | 864 lines | 291 lines |

The test files absorbed the inline rejection shapes, so they barely shrank; the saving is in fixture files.

**What landed**

- **Bug fix:** a closure in `#[Arg(rules:)]` on a model binding dropped the automatic `exists` rule
  (`AsActionField::resolveModelBindingRules()`). The closure is now resolved first and `exists` prepended, as
  `DiscoveredInputType` already did.
- **`GraphQLDiscovery` split.** It keeps the `discover()`/`apply()` orchestration. Extracted into `Discovery/`:
  - `SchemaValidator`: the cross-class checks `apply()` runs (`validate()`: Rebing name clash, duplicate operations,
    input-typed field args, unregistered class references) plus `assertNameAvailable()`.
  - `TypeUsage`: one walker over type references (`ofAction()`, `ofType()`, `references()`); decides which types to
    register (`typesToRegister()`) and which implicit enums are missing (`missingEnums()`).
  - `ReturnTypeResolver`: resolves an action's return type from `type:`/`of:`, a type builder or inference
    (returns `ResolvedReturn`).
  - `FieldMembers` and `FieldMember`: member checks, field naming and the duplicate-name check, shared by
    `TypeCollector` and `InputCollector`.
- **Shared rule, authorize and lookup handling:**
  - `ArgumentRules` merges and normalises rules for `RuleProviderRegistry` and `AsActionField`.
  - `DiscoveredModelBinding` owns the route-key lookup (`lookup()`) and the `exists` rule.
  - `Authorize` keeps the shape rules next to each other, still per target: `verify()` (field), `verifyOnAction()`,
    `verifyOnParameter()`, `verifyOnProperty()`. Messages stay per target.
  - `DiscoveredType::attributes()` is shared by the adapters, `Query`/`Mutation` share `ActionAttribute`, and
    `Injections` fills root/context/info.
- **Dead code removed:** `TypeRegistry::classOf()`, `TypeRef::fromAction()`, `TypeRef::asNullable()`, unreachable
  `match` defaults. `DiscoveredAction` matches `Query`/`Mutation` by `instanceof`, so subclasses work.
- **Tests:** shared helpers in `tests/Pest.php` (`expectRejected()`, `discoverGraphQL()`, `discoveredTypes()`, the
  discovery-cache round trip, `queryGraphQL()`, `closureMapper()`, …); fixtures follow the convention in
  [Rules for every work package](#rules-for-every-work-package). Root-level action fixtures merged into themed classes
  (`ScalarActions`, `PaginatedActions`, `SortableQueries`, `DeprecatedQueries`, `SchemaQueries`); the inferrer's
  rejections are unit tested once in `TypeInferrerTest`; `#[Authorize]` rejections are one matrix in
  `AuthorizeShapeTest`; loader option shapes are verified directly on `KeyLoader`/`RelationLoader`.

**Verified constraints for later WPs**

- Anonymous classes work through `ClassReflector` for `discover()`-time rejections, and their items serialize. An
  action on an anonymous host cannot be resolved (`Container::call('Class@method')` splits on the `@` in the class
  name), so valid action fixtures stay named.
- An anonymous subclass takes its parent's namespace (`Illuminate\…\Model@anonymous`), so `SkippedMembers` skips every
  member: a rejection on a member of a model or Rebing subclass cannot be tested anonymously.
- `class_basename()` of an anonymous class is garbage; messages embedding a short name or derived type name need `%2$s`
  or a named fixture.
- The first error aborts a class, so one fixture tests one shape.
- `tests/Fixtures` is not scanned at boot: Tempest iterates the root `autoload.psr-4` namespaces only
  (`AutoloadDiscoveryLocations.php:79`), and `Tests\` is `autoload-dev`.
- `apply()` runs `validate()` before the `configurationIsCached()` check, so an `apply()`-time rejection fires even
  with a config-cached row; a config-cached row on such a shape asserts nothing.
- A zero-argument non-static closure in a dataset row is invoked by Pest; `static fn` rows error.

**Rejected after evaluation**

| Item | Why not |
|------|---------|
| R13 drop the AsArgs/Omitted cache replays | They are the only end-to-end proof that flattened/omittable DTOs resolve after the cache. |
| S13 collapse `DiscoveredArg` `type`/`nullable`/`typeRef` into a `TypeRef` | Changes a cached DTO shape; do it with WP5.1, which adds factory `Field.args`. |
| S14 `ActionDecorator::decorate(): void` | Public contract; a BC break for no gain. |
| S15 move `assertLoadable` into the loader side | The verification stays generic in the collector. |
| Arg-definition half of S11 (`DiscoveredArg::definition()`) | The two arg-definition arrays differ in SDL-visible ways; unifying them changes the SDL. |

**Accepted coverage drop:** the naming-strategy rename and the `#[Relation]` under snake_case combination are no longer
tested (the `Naming/Author`, `Manuscript` fixtures went). `BatchLoadingTest` still covers "relation loads by PHP name,
not field name".

**Open follow-ups**

- An `Injection` enum replacing the `'root'|'context'|'info'` strings, and `Injections` with it.
- `TypeUsage::usedInputs()` does not strip `[]!` from a named arg type (`#[Arg(type: 'BookInput!')]`); pre-existing.
- `DiscoveredAction` re-declares what `ClassifiedParameters` already holds. Revisit with WP5.1, together with S13.
- `InputHydrator` re-reads `#[Field]` by reflection at resolve time instead of using the serialized field (low
  priority; a fix needs a class-keyed index on `TypeRegistry`).

## Priorities

| Priority | Phase | Why |
|----------|-------|-----|
| P0 | 0. Foundations | Everything else builds on the type reference, the registry and registration. |
| P1 | 1. Object types and enums | The core feature: plain classes as types, inferred from action returns. |
| P1 | 2. Inputs | The other half of the core feature: input classes inferred from action parameters. |
| P2 | 3. Naming and inference hooks | Needed by apps with snake_case schemas, and replaces the hard-coded scalar handling. |
| P2 | 4. Models, field authorization, batch loading | Makes Eloquent models usable as types without N+1 queries. |
| P3 | 5. Dynamic types and extension | Factories, providers, replacing and extending types (e.g. resources from another package); schema-scoped types. |
| P4 | 6. Interfaces and unions | Low priority by request. Designed, but built last. |
| — | 7. Documentation and release | Runs alongside: each WP updates the docs it affects; this phase does the final pass. |

## Dependency graph and parallel tracks

```
WP0.1 Parameter classifier refactor ──► WP0.2 TypeRef + TypeRegistry ──► WP0.3 Registration + schema test helper
                                                                                │
                                                                                ▼
                     WP1.1 Object types + TypeCollector ──► WP1.2 Action return inference
                           │   │                     │
                           │   └──► WP1.3 Enums       │
                           │                         ▼
                           ├────────────────► WP2.1 Input types + inferred input args ──► WP2.2 #[AsArgs] ──► WP2.3 Omitted
                           │
                           ├──► WP3.1 Naming strategy (touches 1.x and 2.x output; schedule after 2.1)
                           ├──► WP3.2 TypeMapper
                           │
                           ├──► WP4.1 FieldDecorator + field #[Authorize]
                           ├──► WP4.2 Eloquent models (hooked properties)
                           └──► WP4.3 Batch loading (#[Load], #[Relation]) ── needs 4.1's FieldDecorator
                                       │
                                       ▼
                     WP5.1 TypeFactory ──► WP5.2 TypeProvider
                     WP5.3 replace: true      WP5.4 #[ExtendType]
                     WP5.5 Schema-scoped types (after the current tracks)
                                       │
                                       ▼
                     WP6.1 Interfaces ──► WP6.2 Unions
                     WP6.3 Validation adapter contract (final step before Phase 7)
```

Phase 0 runs in sequence: WP0.1 and WP0.2 both edit `GraphQLDiscovery.php` and `DiscoveredAction.php`, so running
them in parallel would conflict.

After WP1.1, three tracks can run in parallel in separate worktrees. WP1.1 introduces a `TypeCollector` with
registration points (see WP1.1), so the tracks add classes instead of all editing `GraphQLDiscovery::discover()`,
`ParameterClassifier` and `DiscoveredObjectType`. Where a track still has to edit a shared file, keep the change small
and rebase often.

- **Track A (core output):** WP1.2 → WP1.3 → WP3.2.
- **Track B (inputs):** starts once WP1.3 lands: WP2.1 → WP2.2 → WP2.3.
- **Track C (runtime behaviour):** starts right after WP1.1: WP4.1 → WP4.3, with WP4.2 in between.

WP3.1 (naming) touches field names everywhere. Schedule it after WP2.1 has merged so it doesn't conflict with two
tracks at once. Phase 5 and Phase 6 come after the tracks merge.

## Rules for every work package

These come from `CLAUDE.md` and the maintainer's standing preferences. Every agent must follow them.

- **Tests first-class.** Every new code path is covered by a Pest test before the WP counts as done. Add them to the
  test file for the area and use the shared helpers in `tests/Pest.php` (`discoverGraphQL()`,
  `discoveredActions()`, `expectRejected()`, `schemaSdl()`, …); check there before writing a local one.
- **Fixtures.** They live under `tests/Fixtures/RebingGraphQL/` (one sub-folder per area: `Types/`, `Inputs/`,
  `Enums/`, `Loaders/`, …), never under `workbench/app`, which is scanned at boot.
  - A valid shape gets a named class, or a method on an existing themed fixture when the test reads by method
    (`discoveredActions(X::class)['method']`).
  - A shape rejected in `discover()` is an anonymous class inline in the test file's rejection dataset: a
    zero-argument non-static closure (`fn() => new #[Input] class {…}`), asserted with `expectRejected()`. The message
    format takes the FQCN as `%1$s` and the basename as `%2$s`.
  - It stays a named class in the group's `Invalid` folder when it is abstract, an enum or an interface; extends an
    `Illuminate\` or Rebing class (an anonymous subclass takes the parent's namespace, so it is skipped as a framework
    class); is used as a type by another class; is resolved at runtime (an anonymous action host cannot be called); is
    rejected at `apply()`; or when its message embeds a short name or a derived type name.
- **Assert the SDL.** Use the schema test helper from WP0.3 to compare the produced SDL with the expectation from the
  API reference. Add end-to-end `postJson('/graphql', …)` tests for resolution behaviour.
- **Style.** `declare(strict_types=1);` in every file, PHP 8.5, Laravel Pint (`composer lint`). Comments clarify
  code only: no architectural rationale in comments, and docblocks at most one line (plus `@param`/`@return` types).
- **Hooks by interface.** Discover attribute decorators by their interface (`FieldDecorator`, `BatchedFieldDecorator`,
  `ActionDecorator`), never by concrete class. Integrations go through a contract with our implementation as one
  adapter (as `Hydrator` and `RuleProvider` already do), never a hard dependency.
- **Reject shapes that can't work** at discovery with a `LogicException` or `RuntimeException` that names the class,
  the member and the fix. See the existing messages in `SchemaValidator`, `TypeCollector` and `ParameterClassifier` for tone.
- **Cache safety.** Everything stored in discovery items must survive `serialize()`: class-strings, scalars,
  attribute instances without closures, and DTOs of those. Build webonyx/Rebing type objects lazily, at
  field-init or type-build time, never during `discover()`.
- **No behaviour change for existing users.** All defaults keep current schemas identical. The existing test suite
  must stay green after every WP.
- **No Python** in shell commands; use `jq`, `awk`, `grep`, `sed`.
- **Commits.** Conventional Commits, English, one concrete change per commit, on a feature branch.
- **Docs.** Update `docs/graphql*.md` at the repository root for the behaviour the WP adds. Phase 7 does a final pass.
- **Import aliases.** The new attributes `Type`, `Field` and `Enum` clash with `Rebing\GraphQL\Support\{Type,Field}`
  and `GraphQL\Type\Definition\{Type,EnumType}`, which `AsActionField` and `GraphQLDiscovery` already import. Alias
  the Rebing and webonyx imports (`as RebingType`, `as GraphQLType`, as the code already does in places).
- **Run** `composer test`, `composer lint` and `composer analyse` (phpstan, which CI runs and which must stay at 0
  errors) before handing back. Report failures with their output.

## Phase 0: Foundations

### WP0.1 Extract parameter classification (refactor, no behaviour change)

**Status:** Done, `bc59fea` (#8).

**Depends on:** nothing.

**Why:** method fields on types (WP1.1), contributor methods (WP5.4) and inferred input args (WP2.1) all classify
parameters the same way actions do. Today that logic is inline in `GraphQLDiscovery::discover()`.

**Scope:**
- Move the per-parameter classification out of `GraphQLDiscovery::discover()` into a `ParameterClassifier`
  collaborator. Classification covers: `ContextualAttribute` skip, `#[Root]`/`#[Context]`/`ResolveInfo`
  injections, model bindings (including parameter `#[Authorize]` and the `#[Can]` rejection), value-object
  compositions via `ActionArgProvider` and `HydratorRegistry`, container injections, and scalar args via
  `discoverActionParameter()`.
- Return one result object (args, injections, containerInjections, argCompositions, modelBindings) that
  `DiscoveredAction` is built from.
- Move the parameter-level assertions with it (`assertNoValueAuthorizationRule`,
  `discoverParameterAuthorizations`, `assertNoParameterAuthorization`, `assertNoArgNameCollisions`).

**Files:** `src/RebingGraphQL/GraphQLDiscovery.php`, a new `src/RebingGraphQL/Discovery/ParameterClassifier.php`
(namespace to be chosen to match the existing `Argument\` sub-namespace).

**Tests:** the existing suite is the test. Add a unit test for the classifier's ordering only if it is not already
covered end to end.

**Done when:** `composer test` is green with no test changes, and `GraphQLDiscovery::discover()` delegates parameter
handling.

### WP0.2 TypeRef and TypeRegistry

**Status:** Done, `f0c0d1b` (#8).

**Depends on:** WP0.1 (both edit `GraphQLDiscovery.php` and `DiscoveredAction.php`).

**Scope:**
- `TypeRef`: a readonly, serializable value object for "a reference to a GraphQL type". It holds one of a
  class-string, a GraphQL type name or a scalar name, plus `list`, `nullable` and `nullableItems`. Named
  constructors `TypeRef::class(...)`, `TypeRef::named(...)` and `TypeRef::scalar(...)`.
- `TypeRegistry` (container singleton): maps class-string to GraphQL name and kind (object, input, enum, interface,
  union), and back. `resolve(TypeRef $ref, Position $position): GraphQLType` builds the webonyx type. It handles
  scalars (`string`, `int`, `float`, `bool`, `void`, plus `ID` and the other built-in names), registered classes,
  and plain names through `GraphQL::type()`, then wraps the result in list and non-null.
- `Position` enum: `Output`, `Input`.
- Change `Query`/`Mutation`: `type:` accepts a class-string as well as a name, and a new `of:` parameter means "a list
  of this" (it implies `list: true`). The `Action` interface gains the matching property. Add `nullableItems:`.
- `AsActionField::type()` resolves through the registry. `ActionTypeBuilder::buildType()` must receive the
  **resolved GraphQL name**. `#[Paginated]` calls `GraphQL::paginate($action->type)`, so give the builder an `Action`
  whose `type` is already the resolved name (clone it, or add a resolved-name property). Keep the `buildType()`
  signature unchanged.

**Files:** new `TypeRef.php`, `TypeRegistry.php`, `Position.php`; changes to `Query.php`, `Mutation.php`,
`Action.php`, `AsActionField.php`, `DiscoveredAction.php`.

**Tests:** registry resolution for every scalar, list and non-null wrapping, `nullableItems`, a class-string that
isn't registered (clear error), and `#[Paginated]` still producing `BookPagination` when `type:` is a class-string
of a hand-registered type.

**Done when:** existing tests pass, and `type:` accepts both forms. Until WP1.1 registers discovered classes, the
class-string form only works for a class the registry is told about in a test.

### WP0.3 Type registration and the schema test helper

**Status:** Done, `785110f` (#8).

**Depends on:** WP0.2.

**Scope:**
- A `DiscoveredType` DTO (serializable): name, class, kind, description, fields (`list<DiscoveredTypeField>`),
  factory class, `replace` flag, interfaces, plus `bindName` derived from a hash, as `DiscoveredAction::withBindName()`
  does. And a `DiscoveredTypeField` DTO: PHP name, GraphQL name, `TypeRef`, source (property, method or factory),
  args (`list<DiscoveredArg>` plus the classified injections), description, deprecation reason, decorators.
- Rebing adapters, built lazily from the DTO: `DiscoveredObjectType extends Rebing\GraphQL\Support\Type`. Leave
  stubs for input, enum, interface and union adapters; each later WP fills in its own.
- **Restructure `GraphQLDiscovery::apply()`.** Today it returns early when `configurationIsCached()` is true, right
  after binding the action singletons. Everything this plan adds must run in both modes, so reorder it into four
  steps:
  1. bind every singleton: actions, and each type as `discovery.rebing_graphql.type.<sha256>` building its adapter;
  2. fill the `TypeRegistry` from the discovered types;
  3. validate references, and decide which input types are used (WP1.2, WP2.1);
  4. only when the configuration is not cached, write config: the schemas as today, plus
     `graphql.types[<name>] = <bindName>`.

  Rebing's `addTypes()` uses a string key as the type name, so the bind name is never instantiated just to read
  `->name`.
- **Fix the per-schema gap:** hand-written Rebing `Type` classes (`DiscoveredField` with `fieldType: 'types'`)
  currently go into `graphql.schemas.default.types`. Write them to `graphql.types` instead. Rebing keeps one type
  registry per process and every schema lists all registered types, so schema contents stay the same. A non-default
  schema that is built first can then resolve them, which today throws `TypeNotFound`.
- **Test helper:** `schemaSdl(string ...$fixtureClasses): string`. Put it next to `discoverGraphQL()` or in
  `tests/Pest.php`. The workbench discovers its own queries and types at boot, so the helper must isolate itself:
  1. empty the boot output: `config()->set('graphql.schemas', [])` (as `GraphQLDiscoveryTest.php:282` already does)
     and `config()->set('graphql.types', [])`;
  2. reset Rebing: `app()->forgetInstance(\Rebing\GraphQL\GraphQL::class)` **and**
     `\Rebing\GraphQL\Support\Facades\GraphQL::clearResolvedInstance(\Rebing\GraphQL\GraphQL::class)`, because
     Rebing's facade caches its resolved instance; then `app()->forgetInstance(TypeRegistry::class)`;
  3. run `discoverGraphQL(...)` over the fixtures and call `apply()`. `app(GraphQLDiscovery::class)` returns a fresh
     instance, so `setItems()` does not touch the boot instance's items;
  4. build the default schema and return `SchemaPrinter::doPrint($schema)`.

  A schema with an empty `Query` prints, but fails `Schema::assertValid()`. The helper only prints; tests that need
  validity add a query fixture. Also add a `buildAllSchemas()` helper that builds and calls `assertValid()` on every
  configured schema, so a test catches lazy resolution errors.

**Tests:** a `DiscoveredType` survives `serialize()`/`unserialize()`; `config:cache` still succeeds
(`GraphQLCacheTest`); the `DiscoveredField` move does not change the workbench schema; a second schema built first can
resolve a hand-written type; with `configurationIsCached()` faked to return true, `apply()` still binds the types and
fills the registry. The cached-config case in `GraphQLCacheTest` is a `->todo`, so that test alone does not prove
cached mode works.

**Done when:** an object type adapter can be registered from a hand-built `DiscoveredType` in a test and queried, and
the helper prints its SDL.

## Phase 1: Object types and enums

### WP1.1 `#[Type]`, `#[Field]` and `#[Ignore]`

**Status:** Done, `5833cfd` and `1b9b668` (#8).

**Depends on:** WP0.1, WP0.3.

**Scope:** see [Object types](#object-types) in the API reference.
- **Narrow the instantiable guard.** `GraphQLDiscovery::discover()` returns early when `! $class->isInstantiable()`.
  That is false for enums, abstract classes and interfaces, so it would hide `#[Enum]` (WP1.3) and abstract
  `#[InterfaceType]` classes (WP6.1). Apply the guard only to the branch for hand-written Rebing classes.
- **`TypeCollector`.** Put field collection in a `TypeCollector` collaborator rather than in `discover()`. It reads
  a class's members into `DiscoveredTypeField`s and exposes registration points that later WPs plug into without
  editing it: field decorators (WP4.1, WP4.3), type mappers (WP3.2), the naming strategy (WP3.1), and member
  filters (WP4.2 skips framework members). `GraphQLDiscovery::discover()` only dispatches to it. The same goes for
  the object type adapter: give `DiscoveredObjectType` one place where each field's definition is built, so decorators
  hook in there.
- The `#[Type]` attribute (class): `name`, `description`. Stub `factory`, `replace` and `naming` as accepted but
  rejected with "not implemented yet" until their WPs land, or leave them out and add them later. Prefer leaving
  them out.
- The `#[Field]` attribute (property, method): `name`, `type`, `of`, `nullable` (`?bool`, null means infer),
  `nullableItems`, `description`, `deprecationReason`. `rules` arrives in WP2.1.
- The `#[Ignore]` attribute.
- `#[Field]` and `#[Ignore]` also target parameters (`TARGET_PARAMETER`), because PHP applies an attribute on a promoted constructor parameter to both the parameter and the property. Read them through property reflection only; the parameter target exists so `newInstance()` doesn't throw.
- Field collection in the `TypeCollector` for classes with `#[Type]`:
  - Public non-static properties, promoted ones included, and properties with a get hook.
  - Public methods only with `#[Field]`. Their parameters go through `ParameterClassifier`.
  - Methods take native `#[\Deprecated]`; properties use `deprecationReason:`.
  - Type inference uses the [inference table](#type-inference), output position. Nullability comes only from `?T`,
    never from a default value.
  - `array`, `iterable` and `Collection` need `of:` or `type:`.
- Resolution:
  - **Every property field gets an explicit resolver** that reads `$root->{$phpName}`. Don't rely on webonyx's
    default resolver: for an `ArrayAccess` root, which includes every Eloquent model, it takes the `offsetGet()`
    branch (`vendor/webonyx/graphql-php/src/Utils/Utils.php:271`). That calls `getAttribute('publishedAt')` and
    returns `null` without running the property hook. The explicit resolver also handles renamed fields (WP3.1)
    without relying on `alias`, and is what field decorators wrap (WP4.1).
  - A method field is called **on the root object**, with arguments mapped from GraphQL args and the container
    filling injected parameters (`$app->call([$root, $method], $mapped)`). Never use `Class@method`: that would
    build a fresh instance.
- Register the class in the `TypeRegistry` as kind `object`.
- Reject at discovery: `#[Field]` on a non-public member, `#[Field]` together with `#[Ignore]`, an unsupported type
  without `of:`/`type:`, and two discovered types with the same GraphQL name. The type name is the class basename
  with a `Type` suffix stripped.

- **Follow-ups from the Phase 0 review:**
  - `DiscoveredObjectType` rejects `#[Arg(rules:)]` on a method field's args, and model-bound or value-object
    parameters, when the schema is built. Once fields are discovered from classes, move those checks into
    `discover()`, so `discovery:cache` fails instead of the first request.
  - Decide whether to keep rejecting rules on method-field args or to wire them in. Rebing validates nested field
    args through `RulesInFields` (`Field::validateFieldArguments()`), so a `rules` entry on the arg definition,
    re-read from the attribute like `AsActionField::resolveRules()`, would run.

**Tests:** fixtures for each field source (promoted, plain, hooked, method with args, method with injected service,
ignored, deprecated property and method, `of: 'string'`, `of: Other::class`, nullable). Assert the SDL from the
reference example, and resolve a query end to end that returns instances.

**Done when:** the `Book` example from the reference produces exactly its SDL and resolves.

### WP1.2 Inference in queries and mutations

**Status:** Done, `08f3955` and `4ea1664` (#10).

**Depends on:** WP1.1.

**Scope:**
- `discoverActionReturnType()`: when the return type is a class registered as `#[Type]`, infer it. A class-string
  reference is stored and resolved lazily, so the order in which classes are discovered doesn't matter. `?Book`
  makes the field nullable.
- `of:` on actions with an `array`/`iterable`/`Collection` return.
- Validation in `apply()`, in step 3 so it runs with and without a config cache: every class-string `TypeRef`
  (actions and type fields) must resolve to a registered class. Otherwise throw, naming the referencing member.
  WP5.2 relaxes this when a `TypeProvider` is registered.
- Update the error message for a non-scalar return to mention `#[Type]`, `type:` and `of:`. The TODO.md "better
  discovery errors" item asks for this.

**Tests:** inference for `: Book`, `: ?Book`, `of: Book::class`, `#[Paginated]` with `type: Book::class`; discovery
order independence (the query fixture discovered before the type fixture); the error for an unknown class.

### WP1.3 Enums

**Status:** Done, `71bfd4a` and `b2bde4c` (#12).

**Depends on:** WP1.1. It can run in parallel with WP1.2.

**Scope:** see [Enums](#enums).
- Any PHP enum (backed or not) referenced by a discovered field, arg or input property becomes a GraphQL enum. Its
  name is the enum basename.
- Optional `#[Enum(name:, description:)]` on the enum, which also registers an enum nothing references. Tempest
  passes enums to `discover()` (`class_exists()` is true for enums), so this works once WP1.1 has narrowed the
  instantiable guard.
  `#[EnumValue(description:)]` on cases; native `#[\Deprecated]` on cases becomes `deprecationReason` (this works on
  PHP 8.5; verified).
- GraphQL values are the case names, and the internal value is the case instance, so resolvers and hydrators
  receive the enum case.
- An enum used as an action arg (scalar-like) becomes an enum arg instead of an error.
- `DiscoveredEnumType` adapter extending Rebing's `EnumType`. webonyx's `EnumType::serialize()` already accepts
  `UnitEnum` instances (`EnumType.php:139-145`), so case instances as internal values work. Don't use webonyx's
  `PhpEnumType`: it reads webonyx's own `Deprecated`/`Description` attributes, not native `#[\Deprecated]`.

**Tests:** SDL for the `Genre` example; an enum property resolves to its case name; an enum action arg arrives as a
case; deprecated and described cases.

## Phase 2: Inputs

### WP2.1 `#[Input]` classes and inferred input args

**Status:** Done, `00b3c62` (#16). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP1.1, and WP1.3 for enum properties.

**Scope:** see [Input types](#input-types).
- `#[Input(name:, description:)]` on a class. Its name is the class basename plus `Input`, unless the name already
  ends in `Input`. `#[Type]` and `#[Input]` may sit on one class (`Address` → `Address` and `AddressInput`).
- Input fields come from public properties, inferred in input position. Nested `#[Input]` classes, `of:` lists of
  them, enums and Eloquent models (an `ID!` arg plus a route-key lookup, with an automatic `exists` rule, and
  `#[Authorize('ability')]` on the property as on a parameter today). `#[Field(rules:)]` adds explicit rules.
- `ParameterClassifier`: a parameter whose class has `#[Input]` becomes an input arg named after the parameter,
  typed as the input object. `#[Arg(name:, description:)]` renames and describes it. Classification order:
  ContextualAttribute → root/context/info → model → `#[AsArgs]` (WP2.2) → `#[Input]` class → `ActionArgProvider` value
  object → container → scalar or enum. A class without `#[Input]` is still injected from the container.
- Hydration: a new built-in `InputHydrator`, tagged with `Hydrator::TAG` after `ComposedFromArgsHydrator`. It claims
  `#[Input]` classes, calls the constructor with named arguments, then sets any remaining public properties. It
  builds enums from their case names and nested inputs recursively, and looks up models by route key. The values it
  receives are keyed by **PHP property name** (map them back before calling the hydrator). For an inferred input arg,
  pass the nested `$args[<argName>]`.
- `#[Field(rules:)]` may be a `Closure`, like `Arg::$rules`. Follow the existing pattern: `DiscoveredTypeField` stores `hasRules: bool`, and the rules are read again from the attribute through reflection at request time (see `AsActionField::resolveRules()`). Never store a `Field` instance in a DTO.
- **Validation goes on the input type's fields, not on the action.** Rebing validates the raw args before
  `resolve()` runs, so nothing is hydrated yet. laravel-validation's nesting (`#[Valid]`) only recurses into values
  that are already objects, so it can't help here. Rebing, however, already does the nesting:
  - `Support/Rules.php` collects the `rules` key from every field of an `InputObjectType` and prefixes the paths
    itself (`input.title`, `input.shipTo.city`, `input.chapters.0.title`, per index);
  - `Support/AliasArguments/AliasArguments.php` renames request keys using each input field's `alias` before
    `resolve()` is called.

  So `DiscoveredInputType::fields()` attaches to each field a `rules` entry combining: laravel-validation's rules for
  that one property (compile the class's rules once and pick the member's entry), `#[Field(rules:)]`, and the
  automatic `exists` rule for a model property. **Don't** change `toArgPath()` or `RuleCompiler::forValues()` for
  this. Flattened `#[AsArgs]` fields (WP2.2) are top-level args, so they keep using the existing flat
  `RuleProvider` path.
- **Emitted only when used:** register an input type with Rebing only if something uses it as an input object (an
  inferred arg, or a property of another used input). `apply()` decides this in step 3, so it holds with and without
  a config cache. Output types are always registered.
- **`#[Authorize]` on an input property** only applies to a model-bound property, where it checks the bound record
  before validation, like on a parameter today. On any other input property it is rejected. On a class that is both
  `#[Type]` and `#[Input]`, the position decides: in output position the field-level meaning from WP4.1 applies, in
  input position this one.
- `DiscoveredInputType` adapter extending Rebing's `InputType`.
- Reject: an `#[Input]` property typed as an interface, a union or an output-only `#[Type]`; an `#[Input]` class in
  output position unless it is also a `#[Type]`.

**Tests:** the `CreateBook` and `Address` SDL; `createBook(CreateBook $input)` end to end, with hydration into the
class; renaming through `#[Arg]`; a nullable input arg; validation errors reported at `input.title` and
`input.shipTo.city`; a model property bound and authorized; an unused input class absent from the SDL; a bare
non-input class still injected from the container.

**Watch out:** `TODO.md` (Tier 1) proposes an `#[Args]` attribute for *raw* args access. Flattening is named `#[AsArgs]`, so the names don't collide, but they read alike. Mention the difference in that TODO item.

### WP2.2 `#[AsArgs]`: flattening an input class into args

**Status:** Done, `3c8a2c5` (#18). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP2.1.

**Scope:**
- An `#[AsArgs]` parameter attribute. The class must have `#[Input]`; otherwise discovery rejects it.
- The class's input fields become the action's (or method field's) own top-level args. Several `#[AsArgs]`
  parameters and plain args can be mixed; any duplicate arg name is rejected through the existing collision check.
- Hydrate with `InputHydrator` from only the args that belong to that class. Validation paths are not prefixed.
- A class used only through `#[AsArgs]` is not emitted as an input type.

**Tests:** the `findBooks(#[AsArgs] BookSearch $search)` SDL and resolution; validation on a flattened field; a
collision; `#[AsArgs]` on a class without `#[Input]` rejected.

### WP2.3 Omitted for partial updates

**Status:** Done, `95f79e3` (#20). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP2.1.

**Scope:**
- A `enum Omitted { case Value; }` sentinel.
- In input position, `Omitted` is removed from a property's union type, and the field becomes optional. An absent
  field hydrates to `Omitted::Value`.
- When `null` is not part of the PHP type (`string|Omitted`), an explicit `null` is rejected by an automatic
  validation rule, because GraphQL accepts `null` for an optional field.
- Reject `Omitted` outside input position, and an `Omitted` property without `Omitted::Value` as its default.

**Tests:** absent, explicit null and present values for `string|Omitted` and `string|null|Omitted`.

## Phase 3: Naming and inference hooks

### WP3.1 Naming strategy

**Status:** Done, `c047a1d` (#19). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP1.1, WP2.1 (merge after both).

**Scope:** see [Naming](#naming).
- Config `discovery.graphql.naming.fields` (type and input fields), `.arguments` (query and mutation args, field
  args, `#[AsArgs]` fields), `.operations` (query and mutation field names). Each takes a `FieldCase` case
  (`Preserve`, `Camel`, `Snake`) or a `class-string<NamingStrategy>`. All default to `Preserve`.
- `NamingStrategy::name(string $phpName): string`.
- `#[Type(naming:)]` and `#[Input(naming:)]` override fields and arguments for one type.
- An explicit `name:` (on `#[Field]`, `#[Arg]`, `#[Query]`, `#[Mutation]`) is always used as written.
- Mapping back:
  - output fields: the explicit property resolver from WP1.1 reads the PHP property, whatever the GraphQL name;
  - input fields: set `alias` to the PHP property name. Rebing's `AliasArguments` renames the request keys before
    `resolve()`, so hydration receives property names for free;
  - method-field args and action args: `DiscoveredAction::toParameters()` (or an equivalent for fields);
  - flat validation messages map back through `toArgPath()`, as today. Nested input paths are Rebing's and already
    use GraphQL names.
- Decide where the config lives: `packages/laravel-discovery/config/discovery.php` has no GraphQL section. Either add
  a `graphql` key there, or give the GraphQL package its own merged config file. Prefer a `discovery.graphql.*`
  section merged by `GraphQLDiscoveryServiceProvider` (`mergeConfigFrom`), so the core package stays unaware of
  GraphQL.

**Tests:** Snake fields with an aliased property; Snake arguments on an action, a method field and an input; an
explicit name kept; operations untouched unless configured; a validation error reported under the snake_case path.

### WP3.2 TypeMapper and the scalar map

**Status:** Done, `f0a8387` (#14). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP1.1.

**Scope:** see [TypeMapper](#typemapper).
- `TypeMapper::map(TypeReflector $type, Member $member): ?TypeRef`, with `TAG = 'graphql.type_mappers'`, wired
  through a `TypeMapperRegistry` like `HydratorRegistry`. The first non-null result wins.
- The `Member` DTO: `name`, `declaringClass`, `position`, `kind` (`Property`, `MethodReturn`, `Parameter`).
- Mappers run once a member or parameter has been classified as a field or arg, so they never preempt model
  binding or container injection. They run before the built-in inference rules. Their result is cached with the
  discovery items, so they must decide from reflection alone.
- The built-in `ScalarMap` mapper reads `discovery.graphql.scalars` (`class-string => GraphQL scalar name`) and
  matches with `is_a`. The default config is empty. No built-in rule infers `ID`.

**Tests:** a fixture mapper turning `id` into `ID`; a `Money` mapper; `ScalarMap` matching `CarbonImmutable` against
a `CarbonInterface` entry; a mapper not preempting model binding.

## Phase 4: Eloquent models, authorization, batch loading

### WP4.1 FieldDecorator and field-level `#[Authorize]`

**Status:** Done, `cb71924` (#11). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP1.1.

**Scope:** see [Field authorization](#field-authorization).
- A `FieldDecorator` interface. Field-level attributes implementing it are collected generically from properties
  and methods, as `ActionDecorator` is for actions. A decorator can wrap the field's resolver and adjust its
  definition (nullability, for example).
- `#[Authorize]` gains `TARGET_PROPERTY` and implements `FieldDecorator`. Bare means `auth()->check()`. `gate:`
  calls `AuthorizationGate::check($root, $args, $context, $info)`. An ability calls
  `Gate::allows($ability, $root)`.
- A denied field resolves to `null` without running the resolver, and is **forced nullable** in the SDL.
  `onDenied: Denied::Error` reports a field error instead.
- **Reuse Rebing's `privacy`** for the `null` mode: Rebing's `Type::getFields()` already wraps a field that has a
  `privacy` key so it returns `null` without calling the resolver. Set `privacy` to a closure that runs the check.
  `Denied::Error` needs our own resolver wrapper, which throws Rebing's client-safe `AuthorizationError`.
- **Where `#[Authorize]` applies on types:** on a property or field method of a `#[Type]`, as above. On a `#[Type]`
  *class*, it is rejected for now (its meaning, every field or the type as a whole, is undecided). For input
  properties and classes that are both `#[Type]` and `#[Input]`, see WP2.1.

**Tests:** each form allowed and denied; the SDL shows the field nullable; `onDenied: Denied::Error`; the resolver is
not called when denied.

### WP4.2 Eloquent models with hooked properties

**Status:** Done, `8ea8d08` (#13). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP1.1.

**Scope:** see [Eloquent models](#eloquent-models).
- `#[Type]` on a class extending `Illuminate\Database\Eloquent\Model`.
- Field collection skips members declared in classes and traits under the `Illuminate\` namespace, such as
  `$exists`, `$timestamps`, `$incrementing` and `$wasRecentlyCreated`.
- Reject a public property declared on a model that is not virtual (`PropertyReflector::isVirtual()`), because it
  would shadow the attribute.
- Models in input position stay `ID` bindings.

**Tests:** a workbench migration and model with hooked properties and casts (an immutable datetime, an enum),
resolved end to end; the framework properties are absent from the SDL; a non-virtual property is rejected. The
behaviour was checked by hand against Eloquent in this repo's vendor folder: reads, set hooks, `isDirty()`,
`toArray()`, `save()` and re-fetching all work.

### WP4.3 Batch loading

**Status:** Done, `1331636` (#15). See [Deviations](#deviations-from-this-plan).

**Depends on:** WP4.1 (`FieldDecorator`), WP4.2 for the Eloquent parts.

**Scope:** see [Batch loading](#batch-loading). A data loader with a per-request registry and a relation resolver
that groups roots by class is the model to follow.
- `BatchLoader::load(array $roots, array $options, array $args): array`, returning one result per root in order.
- The `BatchedFieldDecorator extends FieldDecorator` interface: `loader(): class-string<BatchLoader>`,
  `options(string $fieldName): array`.
- `#[Load(LoaderClass::class, ...$options)]`: named arguments become the options.
- `#[Relation(?string $relation = null)]`: sugar for `RelationLoader` with `relation` defaulting to the member name.
- A `Loaders` registry **per GraphQL execution**, not per container scope. Laravel's HTTP kernel never calls
  `forgetScopedInstances()` (only the queue worker and Octane do), so a `scoped()` binding would leak batches
  between two requests in one test, or in any long-running process. Create a fresh registry at the start of each
  execution, for example from a Rebing execution middleware (`Support/ExecutionMiddleware`), and reach it through
  the resolver context or a holder that the middleware resets.
- A field returns a webonyx `Deferred`, and roots are collected per batch key: loader class plus a hash of the
  options and the field args. webonyx queues `Deferred`s and runs them after the sibling pass, so the first one to
  execute sees every collected root, and the loader runs once for the batch. A
  `Loaders::defer(string $loader, object $root, array $options = [])` method is the escape hatch inside a field
  method.
- Built-ins:
  - `RelationLoader` loads the relation on the roots that haven't loaded it yet, then reads `getRelation()`. Group
    the roots by class before loading.
  - `KeyLoader`, with options `model`, `key` (a property on the root), `column` (default: the key name) and `many`.
- **`#[Relation]` fields require `type:` or `of:`** on their `#[Field]`: `type:` for a single record, `of:` for a
  list. Nothing is inferred from the relation, so discovery never instantiates a model. A `#[Relation]` field
  without either is rejected at discovery.
- A result list of the wrong length is a `LogicException`. A `null` result on a non-null field is a field error.
- Reject an unknown `key:` for `KeyLoader` when it can be checked statically, and `#[Relation]` on a non-model class.

**Tests:** count queries (`DB::enableQueryLog()`): `N` parents with a relation produce two queries, not `N + 1`;
args are part of the batch key; already loaded relations aren't reloaded; `KeyLoader` on a DTO; a custom
`BatchedFieldDecorator` fixture (`#[Files]`-style); registry isolation between requests.

## Phase 5: Dynamic types and extension

### WP5.1 TypeFactory

**Status:** To do.

**Depends on:** WP1.1, and WP2.1 for the input variant.

**Where the code is now:** collected members go through `Discovery/FieldMembers` (shared by `TypeCollector` and
`InputCollector`), and the adapters share `DiscoveredType::attributes()`. `DiscoveredArg` still stores `type`,
`nullable` and `typeRef` for one fact; collapsing it into a `TypeRef` was deferred to this WP (see [Simplification
audit](#simplification-audit)), as were `DiscoveredAction` re-declaring `ClassifiedParameters`.

**Scope:** see [Dynamic types](#dynamic-types).
- `#[Type(factory:)]` and `#[Input(factory:)]` take a `class-string<TypeFactory>`.
- `TypeFactory::fields(TypeContext $context): iterable<Field>`, resolved from the container.
- `TypeContext`: `name`, `class`, `kind` (`Position::Output|Input`, the enum from WP0.2), `naming`, `declaredFields`.
- `Field` doubles as the factory's value object. It gains `resolve: ?Closure($root, array $args, $context,
  ResolveInfo $info)` and `args: array<string, Field>`.
- The factory runs when the adapter builds its fields (Rebing's `fields()`), not in `discover()`. Its output is not
  in the discovery cache.
- A factory field whose name the class already uses is a `LogicException` at type build.

**Tests:** static plus factory fields merged; an output and an input variant from one factory; a `resolve` closure;
factory args; a duplicate name rejected; factory dependencies injected.

### WP5.2 TypeProvider

**Status:** To do.

**Depends on:** WP5.1.

**Scope:**
- `TypeProvider::types(): iterable<TypeDefinition>`, discovered by interface. Only the class name is cached.
- `TypeDefinition`: `name`, `kind`, `class` (optional: lets inference and type resolution map a PHP class to this
  type), `interfaces`, `description`, and `fields` (a closure taking a `TypeContext`).
- Register through our own `afterResolving(\Rebing\GraphQL\GraphQL::class)` hook: call `addType()` and add to the
  `TypeRegistry`. Never write these to config. Add the hook in `GraphQLDiscoveryServiceProvider::register()`, not in
  `apply()`: Laravel doesn't run an `afterResolving` callback retroactively, so a hook added after something has
  already resolved `GraphQL` would never fire. The hook reads the provider classes from the `TypeRegistry`, which
  `apply()` fills.
- When any provider is registered, the unknown-class check from WP1.2 moves from `apply()` to schema build. The
  check now lives in `SchemaValidator::assertClassReferencesRegistered()` (private, called from `validate()`, which
  `GraphQLDiscovery::apply()` runs), so that is the method to gate.
  `TypeUsage::missingEnums()` and `typesToRegister()` decide which implicit types get registered.

**Tests:** a provider yielding two types from an array "meta"; a query returning `: ProvidedClass` infers the
provider's type; the deferred error when neither registry knows a class.

### WP5.3 Replacing a type: `replace: true`

**Status:** To do.

**Depends on:** WP1.1, and WP2.1 for inputs.

**Where the code is now:** which discovered types get registered is decided by `Discovery/TypeUsage::typesToRegister()`;
the duplicate-name check is `SchemaValidator::assertNameAvailable()`, and `validate()` runs the cross-class checks at
`apply()`. Members are read through `FieldMembers`.

**Scope:** see [Extending types](#extending-types).
- `#[Type(replace: true)]` and `#[Input(replace: true)]` on a subclass of a discovered type of the same kind. The
  subclass takes over the parent's GraphQL name. Both classes map to it in the registry, and class-to-type lookup
  picks the most specific registered class.
- The subclass's fields are its own collected fields, inherited members included.
- For inputs, `InputHydrator` builds the subclass.
- Reject: `replace: true` when the parent is not a discovered type of the same kind; two classes replacing the same
  parent.

**Tests:** an output replacement adds a field; a resolver returning the parent class still resolves (subclass-only
fields are `null`); an input replacement hydrates the subclass into a resolver typed against the parent; both
rejections.

### WP5.4 `#[ExtendType]` contributors

**Status:** To do.

**Depends on:** WP1.1, WP0.1, and WP5.1 for factory contributors.

**Where the code is now:** read contributor members through `Discovery/FieldMembers` (member checks, field naming and
the duplicate-name check) and `ParameterClassifier`, as `TypeCollector` does; do not re-implement them.

**Scope:**
- `#[ExtendType(Target::class | 'GraphQLName')]` on a class. Its `#[Field]` methods are added to the target output
  type. The contributor is resolved from the container, and `#[Root]` receives the parent object.
- A contributor implementing `TypeFactory` adds dynamic fields.
- Fields merge in discovery order. A duplicate field name is a `LogicException`.
- Reject `#[ExtendType]` that targets an input type, with a hint to use `#[Input(replace: true)]`.

**Tests:** the `UserBilling` example; `#[Authorize]` on a contributor field; a factory contributor; a duplicate field;
targeting an input.

### WP5.5 Schema-scoped types

**Status:** To do.

**Depends on:** WP5.1–5.4, which decide which types exist and under which name. Scheduled after the current tracks,
by request.

**Where the code is now:** the reachability graph this WP needs is `Discovery/TypeUsage` (`ofAction()`, `ofType()` and
`references()`), one walker over return types, args, fields, field args and input properties, replacing five parallel
walks. The cross-schema check belongs in `SchemaValidator::validate()`.

**Problem:** Rebing accepts `graphql.schemas.<name>.types`, but doesn't isolate them. `buildSchemaFromConfig()`
(`GraphQL.php:450-486`) passes them to `addTypes()`, the one process-wide registry `graphql.types` also fills, and each
schema's `types` closure lists every registered type. So per-schema keys only change *when* a type is registered:
once `admin` is built, its types show up in `default`'s introspection, and the result depends on build order in
long-running processes (Octane, workers, tests). Writing discovered types to the per-schema key alone is therefore
cosmetic.

**Scope:**
- **Assignment.** Compute each schema's types from its actions (`#[Schema]` / `schema:`): return and arg types,
  followed transitively through fields, field args and input properties. A type reached from several schemas belongs
  to each of them. Explicit placement for types nothing reaches (an unreferenced `#[Enum]`, interface implementations,
  union members): `#[Type(schema: ...)]` / class-level `#[Schema]`; without it they stay global.
- **Isolation.** Each schema's `types` list and `typeLoader` only expose its own types plus the global ones. Most
  likely through a `GraphQL` subclass bound in the container that overrides `buildSchemaFromConfig()`.
- **Validation.** An action in schema `admin` that references a type placed only in `default` is a `LogicException`
  at `apply()`, naming the action, the type and both schemas.
- **Opt-in.** Default stays global (today's behaviour, schemas unchanged), behind a config flag or the first explicit
  `schema:` on a type.
- Hand-written Rebing types in `graphql.types` stay global; ones in `graphql.schemas.<name>.types` are scoped.

**Verify first:** that Rebing's `GraphQL` binding can be swapped cleanly (its service provider, the facade, the
`afterResolving(GraphQL::class)` hook that reads `graphql.types`, and the schema cache in `GraphQL::schema()`). This is
a deeper integration than the rest of the plan; if it can't be done cleanly, stop and report before building it.

**Tests:** two schemas with disjoint types, each introspecting only its own, regardless of build order; a shared type
in both; an explicitly placed unreferenced type; the cross-schema reference rejected; defaults leave the workbench
schema unchanged.

## Phase 6: Interfaces and unions (low priority)

### WP6.1 Interfaces

**Status:** To do.

**Depends on:** WP1.1. Schedule it last.

**Where the code is now:** read members through `Discovery/FieldMembers`. `TypeKind::attribute()` currently maps
`Interface` and `Union` to `'Type'`; give `Interface` its own attribute name here (and `Union` in WP6.2), since the
name appears in rejection messages.

**Scope:** see [Interfaces](#interfaces).
- **How interfaces are found.** Tempest only passes application files to `discover()` when `class_exists()` is true
  (`vendor/tempest/discovery/src/BootDiscovery.php:209`), and that is false for interfaces. So a PHP interface
  never reaches `discover()` itself. Instead, find `#[InterfaceType]` interfaces through the types that implement
  them: for each discovered `#[Type]` class, walk `ClassReflector::getInterfaces()` and its parent classes, and
  collect the ones carrying `#[InterfaceType]`. An abstract class with `#[InterfaceType]` *is* passed to
  `discover()` (once WP1.1 has narrowed the instantiable guard), and is also found through its subclasses.
  - Consequence, to document: an `#[InterfaceType]` interface that no discovered type implements is not in the
    schema.
- `#[InterfaceType(name:, description:, resolver:)]` on an interface or abstract class. Its fields come from PHP 8.4
  interface properties (`public string $id { get; }`) and `#[Field]` methods.
- A `#[Type]` class implementing it gets `implements` automatically and inherits the field configuration, which the
  class may refine.
- Type resolution: look up the value's class (walking parents, most specific registered class wins), or use
  `resolver: class-string<TypeResolver>`. `TypeResolver::resolve(mixed $value, mixed $context, ResolveInfo $info):
  string` returns a class-string or a GraphQL name.
- `DiscoveredInterfaceType` adapter extending Rebing's `InterfaceType`.

**Tests:** the `Node`/`Titled` SDL; a query returning the interface resolves to the concrete type; a resolver for
array values.

### WP6.2 Unions

**Status:** To do.

**Depends on:** WP6.1 (shares `TypeResolver` and the class lookup).

**Where the code is now:** `TypeKind::attribute()` maps `Union` to `'Type'` and must return `'Union'`. The PHP union
message lives in `TypeInferrer` only (the collectors delegate to it), so change it there.

**Scope:** see [Unions](#unions).
- `#[Union(name:, description:, types:, resolver:)]` on a marker interface. Members are the `#[Type]` classes
  implementing it, settled in `apply()`, or an explicit `types:` list of class-strings and GraphQL names.
- **How unions are found.** Like interfaces (WP6.1), the marker never reaches `discover()` itself. Collect markers
  through the interfaces of discovered `#[Type]` classes, and through references: a field, action return or `of:`
  that names a `#[Union]` marker registers it. Consequence, to document: a union with only an explicit `types:` list
  that nothing references is not in the schema, which costs nothing, since an unreferenced union is unusable anyway.
- `: SearchResult` and `of: SearchResult::class` infer the union.
- Reject: a PHP union type (`Book|Magazine`) on a field or return, with a hint to declare a marker; a member that is
  a scalar, interface, enum, input or undiscovered class; a marker that is also an `#[InterfaceType]`; a union in
  input position.
- Document the `TypeResolver` limitation: it only picks the type and never changes the value. Fields resolve against
  the raw value, so `#[Field]` methods, property hooks and field `#[Authorize]` abilities need an object of the
  member class.
- `DiscoveredUnionType` adapter extending Rebing's `UnionType`.

**Tests:** discovered and explicit members; reuse in two places under one name; the search-hit resolver example from
the reference; each rejection.

## Phase 6b: Validation adapter contract

### WP6.3 Move validation-library knowledge into its adapter

**Status:** To do.

**Depends on:** WP2.1, WP2.2. Scheduled as one of the final steps, before Phase 7, by request.

**Problem:** the core still knows about laravel-validation. `ParameterClassifier` rejects its `#[Can]` on a
model-bound parameter by class-name string (`VALUE_AUTHORIZATION_RULE`), and nested rules (`#[Valid]`, `#[ListOf]`,
`#[Each]`) on `#[Input]` properties are not applied, because WP2.1 attaches rules per input field. WP2.1 had
discovery-time rejections for both on input properties; they were removed again so the core carries no library
knowledge, which leaves those shapes silently unvalidated until this WP lands.

**Where the code is now:** the hooks are `InputCollector::propertyField()` and `ParameterClassifier` (the `#[Can]`
block in `assertNoValueAuthorizationRule()`); the `#[Authorize]` shape rules are already in one place,
`Authorize::verifyOnParameter()` and `Authorize::verifyOnProperty()`, and stay there. Rules merging goes through
`Argument/ArgumentRules`; `rulesForInput()` replaces the per-field handling in `DiscoveredInputType` and
`AsActionField`.

**Scope:**
- **Discovery-time contract on the adapter.** An optional interface next to `RuleProvider`, e.g.
  `ArgumentDiscoveryVerifier` with `verifyParameter(ParameterReflector, VerifiedMember)` and
  `verifyInputProperty(PropertyReflector, VerifiedMember)`. `VerifiedMember` carries a label for messages, the bound
  model class (if any) and the position. `ParameterClassifier` and `InputCollector` call every tagged `RuleProvider`
  implementing it, and hold no library class names. `LaravelValidationRules` implements it and owns the `#[Can]`
  rejection (parameter and input property), using `Can::class`.
- **Support nested rules instead of rejecting them.** `InputRuleProvider::rulesForInput()` returns the rules for a
  whole input class keyed by relative dot path (`tags.*`, `chapters.*.title`), plus messages. `AsActionField` walks
  every input object through `InputObjects` (which yields the validation path), prefixes the relative rules
  (`input.tags.*`) and merges them into the rules Rebing validates. The per-field `rules` closure keeps only
  `#[Field(rules:)]` and the automatic `exists` rule. Flattened `#[AsArgs]` inputs follow the same path.
- Remove the docs caveat that nested rules on input properties are not applied.

**Tests:** `#[Can]` rejected via the adapter on a parameter and an input property; no rejection with the adapter
untagged; `#[Each]`, `#[ListOf]` and `#[Valid]` on input properties validated at the right nested path, including
inside lists and `#[AsArgs]`; custom messages on nested rules.

## Phase 7: Documentation and release

**Status:** To do.

- Split the documentation: `docs/graphql.md` gets a short "Types" section that links to a new `docs/graphql-types.md`
  (object types, enums, inputs, naming, models, loaders, factories, extension, interfaces, unions). Use the
  `document` skill.
- Update `packages/laravel-discovery-graphql/README.md`'s documentation list and `CLAUDE.md`'s GraphQL section, which
  describes the architecture for future agents.
- Prune `TODO.md`: remove the Tier 2 and Tier 3 items this work ships (union and interface return types,
  `#[GraphQLType]`, `#[GraphQLInputType]`, `#[GraphQLEnum]`, `#[GraphQLInterface]`, list/non-null wrappers).
- The CHANGELOG is generated by `git-cliff` at release time, so commit messages must describe each feature.

## Risks and things to verify first

1. **Rebing registry timing.** Rebing reads `graphql.types` in `afterResolving(GraphQL::class)`
   (`vendor/rebing/graphql-laravel/src/GraphQLServiceProvider.php:128`; this vendor checkout is `dev-master`, not a
   tagged 10.x, so re-check the line on upgrade). Writes from our `boot()` land in time only if nothing resolves
   `GraphQL` before discovery boots. With the provider order in `tests/TestCase.php` that holds: every `register()`
   runs first, Rebing's `boot()` only loads routes, and `DiscoveryServiceProvider::boot()` writes config before
   `GraphQL` is first resolved. WP0.3 keeps a test for it.
2. **Config caching.** `GraphQLCacheTest` has a `->todo` for "config:cache + refreshApplication()". Type bindings use
   the same bind-name approach as actions, so they inherit that issue. Don't fix it as part of this work, but don't
   make it worse. Because that case is a `->todo`, running `GraphQLCacheTest` does not prove cached mode works; the
   faked `configurationIsCached()` test from WP0.3 covers `apply()` instead.
3. **Type instance caching in tests.** Rebing caches built types in `typesInstances`, and its facade caches the
   resolved `GraphQL` instance. The schema helper must reset both (WP0.3), or tests will see types from earlier
   tests.
4. **Discovery doesn't see interfaces.** Tempest skips application files whose class fails `class_exists()`, which
   includes every interface. WP6.1 and WP6.2 find them through discovered types instead.
5. **Tempest `TypeReflector` on PHP union types.** `getName()` on `string|Omitted` returns the class first
   (`Omitted|string`), because PHP reorders the union. `split()` works and `isNullable()` is correct for
   `string|null|Omitted`. WP2.3 and WP6.2 must not rely on the textual order.
6. **Property hooks via reflection.** Check that Tempest's `getPublicProperties()` (or native reflection) lists
   virtual hooked properties, interface properties (WP6.1) and promoted properties consistently.
7. **`#[AsArgs]` next to the planned `#[Args]`.** Flattening is `#[AsArgs]`; the raw-args item in `TODO.md` keeps
   `#[Args]`. Keep the two clearly apart in docs and error messages.
8. **Cost per field.** Every property field gets an explicit resolver (WP1.1), and decorated fields wrap it. Keep
   the resolvers as plain closures without container calls, and add a rough benchmark (a list of a few thousand
   objects) to WP1.1's tests, so a later decorator can't make every field expensive unnoticed.

## Review changes

A review checked this plan against the codebase and the vendor code, and ran the existing suite (501 passed,
1 skipped). These changes came out of it:

| Finding | Where it landed |
|---|---|
| Interfaces never reach `discover()`, and the instantiable guard hides enums and abstract classes | WP1.1 narrows the guard; WP6.1 and WP6.2 find interfaces and unions through discovered types |
| webonyx's default resolver reads Eloquent models through `ArrayAccess` and skips property hooks | WP1.1: an explicit resolver for every property field |
| Nested input validation can't work through laravel-validation before hydration, and Rebing already prefixes and aliases input fields | WP2.1: rules on input fields; WP3.1: `alias` on input fields; no `toArgPath()` or `forValues()` changes |
| A container-scoped loader registry leaks between requests | WP4.3: one registry per GraphQL execution |
| The schema helper didn't isolate from the workbench or reset Rebing's facade | WP0.3: isolation steps |
| `apply()` returns early with a cached config | WP0.3: `apply()` restructured into four steps |
| A `TypeProvider` hook added from `apply()` may never fire | WP5.2: hook added in `register()` |
| Typing `#[Relation]` fields from the relation needs a model instance | WP4.3: `type:` or `of:` is required |
| Parallel tracks would edit the same files | Phase 0 runs in sequence; WP1.1 adds the `TypeCollector` |
| `#[Authorize]` meaning on input properties, shared classes and type classes | WP2.1 and WP4.1 define it; WP4.1 reuses Rebing's `privacy` |

The review also confirmed:
- Rebing's single type registry, and the `DiscoveredField` move;
- that `graphql.types[<name>] = <bindName>` works;
- the serialization approach;
- the provider order;
- interface-based attribute lookup;
- reflection of hooked, promoted and interface properties;
- the `Deferred` batching model;
- the `#[Paginated]` resolved-name approach;
- that `tests/Fixtures` isn't scanned at boot.

---

## API reference

Condensed from the API proposal (draft 3). When this section and the proposal disagree, this section wins.

### Type inference

One mapping serves properties, method returns, action returns and parameters. An explicit `type:` always wins.
Nullability only ever widens: a nullable PHP type makes the field nullable, nothing makes it non-null.

| PHP type | Output position | Input position |
|---|---|---|
| anything a `TypeMapper` claims | the mapper's result | the mapper's result |
| `string` `int` `float` `bool` | `String!` `Int!` `Float!` `Boolean!` | same |
| `?T`, or a default value | only `?T` makes it nullable | `T`, optional, with its default |
| `void` | `Null` (action returns only) | n/a |
| a `#[Type]` class | its type | error, unless also `#[Input]` |
| an `#[Input]` class | error, unless also `#[Type]` | its input type; as an action parameter, an arg named after the parameter |
| a PHP enum | the enum | the enum |
| an `#[InterfaceType]` | the interface | error |
| a `#[Union]` marker | the union | error |
| a PHP union (`Book\|Magazine`) | error, hint: declare a `#[Union]` marker | error |
| an Eloquent `Model` | its type, if the model is a `#[Type]` | `ID!` plus a route-key lookup (wins over `#[Type]`/`#[Input]`) |
| `array`, `iterable`, `Collection` | needs `of:`: `[X!]!`, or `[X]!` with `nullableItems: true` | same |
| any other class | error | container injection (actions only) |

`type:` and `of:` accept a class-string, a GraphQL type name (how hand-written Rebing types are referenced) or a
scalar name (`'string'`, `'ID'`). The PHP type `string` never infers `ID`.

### Object types

```php
#[Type(description: 'A published book')]
final class Book
{
    public function __construct(
        #[Field(type: 'ID')] public string $id,
        public string $title,
        public ?string $subtitle,
        public Genre $genre,
        public AuthorSummary $author,
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
```

```graphql
"A published book"
type Book {
  id: ID!
  title: String!
  subtitle: String
  genre: Genre!
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

`#[Field]`: `name`, `type`, `of`, `nullable` (null = infer), `nullableItems`, `description`, `deprecationReason`,
`rules` (input position only); for factories also `resolve` and `args`.

### Inference in actions

```php
#[Query] public function book(#[Arg(type: 'ID')] string $id, Books $books): ?Book {}
#[Query(of: Book::class)] public function books(Books $books): array {}
#[Query(type: Book::class)] #[Paginated] public function bookPage(Books $books, Pagination $page): LengthAwarePaginator {}
```

```graphql
type Query {
  book(id: ID!): Book
  books: [Book!]!
  bookPage(page: Int = 1, limit: Int = 20): BookPagination!
}
```

### Input types

```php
#[Input]
final readonly class CreateBook
{
    public function __construct(
        #[Min(2), Max(255)] public string $title,
        public Genre $genre,
        #[Authorize('attach')] public Publisher $publisher,   // Eloquent: ID + lookup
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
    #[Query(of: Book::class)] public function findBooks(#[AsArgs] BookSearch $search): array {}
}

#[Input]
final readonly class BookSearch
{
    public function __construct(public ?string $term = null, public ?Genre $genre = null, #[Between(1450, 2100)] public ?int $year = null) {}
}

#[Input]
final readonly class UpdateBook
{
    public function __construct(
        public string|Omitted $title = Omitted::Value,          // absent → Omitted, null → rejected
        public string|null|Omitted $subtitle = Omitted::Value,  // absent → Omitted, null → null
    ) {}
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
  draftBook(
    "Draft contents"
    data: CreateBookInput
  ): Book!
}

type Query {
  findBooks(term: String, genre: Genre, year: Int): [Book!]!
}

# BookSearchInput is not emitted: nothing uses it as a nested input.
input UpdateBookInput { title: String subtitle: String }
```

### Enums

```php
#[Enum(description: 'Shelf a book is filed under')]
enum Genre: string
{
    case Fiction = 'fiction';
    #[EnumValue(description: 'Biographies, essays, history')]
    case NonFiction = 'non_fiction';
    #[\Deprecated('Use Fiction')]
    case Novel = 'novel';
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

### Naming

```php
// config: discovery.graphql
'naming' => [
    'fields'     => FieldCase::Snake,     // type and input fields
    'arguments'  => FieldCase::Snake,     // query/mutation args, field args, #[AsArgs]
    'operations' => FieldCase::Preserve,  // query and mutation field names
],
'scalars' => [CarbonInterface::class => 'DateTime'],
```

`public ?CarbonImmutable $publishedAt` becomes `published_at: DateTime`, resolved through `alias: 'publishedAt'`. An
explicit `name:` is used as written. Enum values stay case names.

### TypeMapper

```php
interface TypeMapper
{
    public const string TAG = 'graphql.type_mappers';
    public function map(TypeReflector $type, Member $member): ?TypeRef;   // null = not mine
}

final class IdsAreIds implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->name === 'id' && in_array($type->getName(), ['int', 'string'], true)
            ? TypeRef::named('ID', nullable: $type->isNullable())
            : null;
    }
}
```

### Eloquent models

```php
#[Type]
class Book extends Model
{
    public string $title {
        get => $this->getAttribute('title');
        set(string $value) { $this->setAttribute('title', $value); }
    }
    public ?CarbonImmutable $publishedAt { get => $this->getAttribute('published_at'); }
    public BookStatus $status { get => $this->getAttribute('status'); }

    #[Authorize('viewSales')]
    public int $copiesSold { get => $this->getAttribute('copies_sold'); }

    #[Field(type: Author::class), Relation]
    public function author(): BelongsTo { return $this->belongsTo(Author::class); }

    protected function casts(): array { return ['published_at' => 'immutable_datetime', 'status' => BookStatus::class]; }
}
```

```graphql
type Book {
  title: String!
  publishedAt: DateTime
  status: BookStatus!
  copiesSold: Int        # forced nullable by #[Authorize]
  author: Author!
}
```

Hooks must be virtual, and members declared under `Illuminate\` are skipped. There are no projection classes and no
cast-based factory.

### Field authorization

```php
#[Type]
final class Member
{
    public string $name;
    #[Authorize('viewContactDetails')] public string $email;            // Gate::allows(..., $member)
    #[Authorize(gate: StaffOnly::class, onDenied: Denied::Error)] public ?string $notes = null;
}
```

`email` becomes `String` (forced nullable). Denied means `null`; `Denied::Error` reports a field error.

### Batch loading

```php
interface BatchLoader
{
    /** @return list<mixed> one result per root, in order */
    public function load(array $roots, array $options, array $args): array;
}

interface BatchedFieldDecorator extends FieldDecorator
{
    public function loader(): string;                       // class-string<BatchLoader>
    public function options(string $fieldName): array;
}

#[Load(KeyLoader::class, model: Author::class, key: 'authorId')] public ?Author $author = null;
#[Field(of: Book::class), Relation] public function books(): HasMany {}   // RelationLoader, relation 'books'
#[Field(name: 'owner', type: User::class, nullable: true), Relation] public function creator(): BelongsTo {}

// an application's own sugar
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Files implements BatchedFieldDecorator
{
    public function __construct(public string $collection = 'default') {}
    public function loader(): string { return FileLoader::class; }
    public function options(string $fieldName): array { return ['collection' => $this->collection]; }
}
```

The batch key is the loader class plus the options and field args. There is one registry per GraphQL execution. `#[Relation]` fields need `type:` or `of:`. A wrong-length
result is a `LogicException`; a `null` on a non-null field is an error. Selection-aware eager loading (Rebing
`SelectFields`) is out of scope.

### Dynamic types

```php
#[Type(factory: ResourceFields::class)]
#[Input(factory: ResourceFields::class)]
class Company extends Model {}

final readonly class ResourceFields implements TypeFactory
{
    public function __construct(private MetaResources $resources) {}

    public function fields(TypeContext $context): iterable
    {
        foreach ($this->resources->forModel($context->class)->fields as $field) {
            yield new Field(name: $field->name, type: $this->graphQLType($field),
                nullable: ! $field->required || $context->kind === Position::Input, description: $field->label);
        }
    }
}

final readonly class ResourceTypes implements TypeProvider
{
    public function types(): iterable
    {
        yield new TypeDefinition(name: 'Tree', kind: Position::Output, class: Tree::class,
            fields: fn (TypeContext $context) => /* iterable<Field> */);
    }
}
```

| | `TypeFactory` | `TypeProvider` |
|---|---|---|
| Contributes | fields for a class | whole types, no class per type |
| Runs | at type build | when Rebing's `GraphQL` is resolved (`afterResolving` → `addType()`) |
| Cached by discovery | the class name only | the class name only |

### Extending types

```php
#[Type(replace: true)]                      // takes over the parent's name
class User extends \Vendor\Accounts\User { public ?string $billingReference = null; }

#[Input(replace: true)]                     // the only way to extend an input
final readonly class CreateUser extends \Vendor\Accounts\CreateUser { /* ... */ }

#[ExtendType(User::class)]                  // output types only
final readonly class UserBilling
{
    public function __construct(private Billing $billing) {}

    #[Field(of: Invoice::class)]
    public function invoices(#[Root] User $user, int $limit = 10): array {}
}
```

A package type meant to be replaced must not be `final`.

### Interfaces

```php
#[InterfaceType]
interface Node { #[Field(type: 'ID')] public string $id { get; } }

#[InterfaceType(description: 'Anything with a title')]
interface Titled
{
    public string $title { get; }
    #[Field] public function excerpt(int $length = 80): string;
}

#[Type] final class Book implements Node, Titled { /* ... */ }
```

### Unions

```php
#[Union(description: 'What the search box returns')]
interface SearchResult {}                                  // members: implementing #[Type] classes

#[Union(types: [Book::class, Magazine::class, 'LegacyPamphlet'])]
interface ShelfItem {}

#[Union(description: 'A hit from the search index', resolver: SearchHitResolver::class)]
interface SearchHit {}

final readonly class SearchHitResolver implements TypeResolver
{
    public function resolve(mixed $value, mixed $context, ResolveInfo $info): string
    {
        return match ($value['_index'] ?? null) {
            'books' => Book::class,
            'authors' => Author::class,
            default => throw new UnexpectedValueException("No GraphQL type for search hit index '{$value['_index']}'."),
        };
    }
}

#[Query(of: SearchHit::class)]
public function search(string $term, SearchIndex $index, int $limit = 20): array {}
```

```graphql
union SearchResult = Book | Author
union ShelfItem = Book | Magazine | LegacyPamphlet
union SearchHit = Book | Author

type Query {
  search(term: String!, limit: Int = 20): [SearchHit!]!
}
```
