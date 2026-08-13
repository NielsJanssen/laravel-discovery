# Changelog

All notable changes to this project will be documented in this file.
## [1.0.0-beta.8] - 2026-08-13

### Bug Fixes

- Move boot logic from register to boot method in DiscoveryServiceProvider
- Use correct shortcut property on ConsoleOption attribute
- Route registration didn’t happen in time
- Discovery cache contained duplicated entries, causing double schedules
- Always register command bootstrap to use Artisan:call in other app code

### Documentation

- Add initial documentation
- Touchups in the documentation
- Add Livewire component section to documentation
- Fix middleware docblocks of routable attributes

### Features

- Discoverable commands
- Discoverable event listeners
- Add argument/option injection for discoverable commands
- Implement basic Command middleware
- Caution middleware
- Implemented test suite
- Route discovery
- Release workflow
- Implement deferred event handling
- Implement discovery cache with optimize integration
- Implement schedule discovery via #[Scheduled] attribute
- Add route name & BackedEnum support
- Add config publication
- Add Discovery for Laravel branding
- Add multi-verb #[Route] attribute
- Reworked Scheduled attribute to allow more native freedom in scheduling before resorting to a closure
- Implement Rebing GraphQL discovery
- Implement GraphQL Schema attribute, and correct default schema fallback
- Implement deprecation and description for GraphQL actions and args
- Allow injection of default GraphQL arguments using #[Context] #[Root] or ResolveInfo as type
- Add support for GraphQL execution middleware on queries and mutations
- Implement #[Authorize] trait for user authentication and permission checking
- Support single action controllers, like Livewire components
- Implemented extensible GraphQL return type builders, with default #[Paginated] attribute
- Dependency injection for additional parameters in Query/Mutation resolve
- Implemented #[Paginated] & #[Sort] decorators for GQL queries and mutations
- Schedule traditional Laravel commands using #[Scheduled] attribute
- Schedule Jobs using #[Scheduled] attribute
- Set scheduled command & method parameters with #[Scheduled(parameters: [])]
- Support multiple class level #[Scheduled] attributes
- Implemented PhpStan at level 10
- Add model route key binding to queries and mutations

### Miscellaneous Tasks

- Enforce strict types across all PHP files
- Remove redundant commands parameter in CommandDiscovery
- Prevent release commits from ending up in the changelog
- *(deps)* Bump actions/checkout from 4 to 6
- Add CLAUDE.md
- Ignore Claude related files and assets in package export
- Support `t serve` comamnd in Workbench
- Update CLAUDE.md after mono-repo switch
- Remove unused stub file
- Upgrade dev dependencies
- Branch aliases for both packages
- *(deps)* Bump actions/checkout from 6 to 7
- *(deps)* Bump actions/cache from 5 to 6

### Other Changes

- Initial commit
- Fix lint & test pipelines
- Disable broken packagist notification in release CI
- Use shortName for classes in docblocks
- Propagate release tags to splits

### Performance

- Skip a few steps when registering commands
- Calculate unique bindName during discovery + restructure apply for clarity
- Only instantiate commands if necessary

### Refactoring

- Replace CommandRegistry with CommandDecorator
- Improve command discovery validation and singleton binding
- Extract anonymous command class into DecoratedCommand
- Fix and simplify command discovery and remove Feature interface
- Flatten namespace by removing Feature layer from src structure
- Improve Schedule discovery
- Move to mono-repo structure
- Reorganize GraphQLDiscovery for readability

### Testing

- Register workbench with discovery to allow tests to use it
- Remove nested Feature namespace from tests
- Cover more GraphQL test cases
- Add Livewire route integration tests


