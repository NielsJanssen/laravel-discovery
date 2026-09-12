# Changelog

All notable changes to this project will be documented in this file.
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


