# Changelog

All notable changes to this project will be documented in this file.

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


