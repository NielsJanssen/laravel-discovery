# Changelog

All notable changes to this project will be documented in this file.
## [1.0.0-rc.3] - 2026-10-04

### Bug Fixes

- Make type builder fields non-null unless nullable
- Resolve TypeRegistry as a singleton without its provider
- Keep the exists rule for closure rules on bindings
- Cache implicit enums after discovery was applied

### Documentation

- Document TypeFactory and record WP5.1
- Document TypeProvider in the guide and plan
- Document replacing types
- Document extending types and record it in the plan
- Document schema-scoped types and record them

### Features

- Add defaultField option for Sort attribute
- Accept class-string and of: on Query and Mutation
- Register Rebing and discovered types in graphql.types
- Discover object types from #[Type] classes
- Infer query and mutation types from #[Type] returns
- Discover PHP enums as GraphQL enum types
- Authorize type fields through FieldDecorator
- Expose Eloquent models as types via hooked properties
- Add TypeMapper hooks and a configurable scalar map
- Batch-load type fields with #[Load] and #[Relation]
- Discover #[Input] classes as inferred input args
- Flatten #[Input] classes into args with #[AsArgs]
- Configure field, argument and operation naming
- Support partial updates with Omitted input fields
- Add TypeFactory fields to discovered types
- Support resolvers and args on factory fields
- Add TypeProvider for output and input types
- Map classes to provided types, defer the class check
- Replace a discovered type with a subclass
- Add fields to a discovered type with #[TypeExtension]
- Extend provided types with #[TypeExtension]
- [**breaking**] Scope each GraphQL schema to its own types

### Performance

- Resolve action field services once per field
- Skip input checks when no input guards a record
- Build an action field's args once
- Plan input hydration once per class
- Call an action straight when its args fill it
- Read an input field's declared rules once

### Refactoring

- Extract ParameterClassifier from GraphQLDiscovery
- Share member reading and rule handling
- Split GraphQLDiscovery into focused classes
- Hold a single TypeRef on DiscoveredArg
- Hold ClassifiedParameters on DiscoveredAction
- Let FactoryFields build fields for any type owner

## [1.0.0-rc.2] - 2026-09-12

### Features

- Infer nullability from the return type alongside an explicit type

## [1.0.0-rc.1] - 2026-09-12

### Bug Fixes

- Key model-binding validation rules onto the GraphQL arg
- Resolve a nullable bound model to null instead of refusing when missing

### Features

- Implement GraphQL Schema attribute, and correct default schema fallback
- Implement deprecation and description for GraphQL actions and args
- Allow injection of default GraphQL arguments using #[Context] #[Root] or ResolveInfo as type
- Add support for GraphQL execution middleware on queries and mutations
- Implement #[Authorize] trait for user authentication and permission checking
- Implemented extensible GraphQL return type builders, with default #[Paginated] attribute
- Dependency injection for additional parameters in Query/Mutation resolve
- Implemented #[Paginated] & #[Sort] decorators for GQL queries and mutations
- Implemented PhpStan at level 10
- Add model route key binding to queries and mutations
- Wire Validation into the GraphQL layer
- Type every rule attribute and reach Laravel rule parity
- Authorize a bound model with #[Authorize('ability')] on the parameter
- Reject #[Can] on a model-bound parameter at discovery
- Default a bound-model authorization failure to Forbidden
- Authorize from inside a resolver with an injectable Authorization

### Performance

- Calculate unique bindName during discovery + restructure apply for clarity

### Refactoring

- Move to mono-repo structure
- Reorganize GraphQLDiscovery for readability
- Reorganize argument classes into their own namespace


