# Changelog

All notable changes to this project will be documented in this file.
## [1.0.0-beta.2] - 2026-05-25

### Documentation

- Touchups in the documentation

### Features

- Add multi-verb #[Route] attribute
- Reworked Scheduled attribute to allow more native freedom in scheduling before resorting to a closure
- Implement Rebing GraphQL discovery

### Miscellaneous Tasks

- Ignore Claude related files and assets in package export
- Support `t serve` comamnd in Workbench

### Other Changes

- Use shortName for classes in docblocks

### Refactoring

- Move to mono-repo structure

## [1.0.0-beta.1] - 2026-05-23

### Bug Fixes

- Discovery cache contained duplicated entries, causing double schedules

### Documentation

- Add initial documentation

### Features

- Implement deferred event handling
- Implement discovery cache with optimize integration
- Implement schedule discovery via #[Scheduled] attribute
- Add route name & BackedEnum support
- Add config publication
- Add Discovery for Laravel branding

### Miscellaneous Tasks

- Prevent release commits from ending up in the changelog
- *(deps)* Bump actions/checkout from 4 to 6
- Add CLAUDE.md

### Other Changes

- Fix lint & test pipelines
- Disable broken packagist notification in release CI

### Performance

- Skip a few steps when registering commands

### Refactoring

- Improve Schedule discovery

### Testing

- Register workbench with discovery to allow tests to use it
- Remove nested Feature namespace from tests

## [0.0.1-alpha.2] - 2026-05-22

### Bug Fixes

- Route registration didn’t happen in time

### Refactoring

- Fix and simplify command discovery and remove Feature interface
- Flatten namespace by removing Feature layer from src structure

## [0.0.1-alpha.1] - 2026-05-22

### Bug Fixes

- Move boot logic from register to boot method in DiscoveryServiceProvider
- Use correct shortcut property on ConsoleOption attribute

### Features

- Discoverable commands
- Discoverable event listeners
- Add argument/option injection for discoverable commands
- Implement basic Command middleware
- Caution middleware
- Implemented test suite
- Route discovery
- Release workflow

### Miscellaneous Tasks

- Enforce strict types across all PHP files
- Remove redundant commands parameter in CommandDiscovery

### Other Changes

- Initial commit

### Refactoring

- Replace CommandRegistry with CommandDecorator
- Improve command discovery validation and singleton binding
- Extract anonymous command class into DecoratedCommand


