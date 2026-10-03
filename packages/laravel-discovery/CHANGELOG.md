# Changelog

All notable changes to this project will be documented in this file.
## [1.0.0] - 2026-10-03

### Bug Fixes

- Always register command bootstrap to use Artisan:call in other app code

### Documentation

- Fix middleware docblocks of routable attributes

### Features

- Support single action controllers, like Livewire components
- Schedule traditional Laravel commands using #[Scheduled] attribute
- Schedule Jobs using #[Scheduled] attribute
- Set scheduled command & method parameters with #[Scheduled(parameters: [])]
- Support multiple class level #[Scheduled] attributes
- Implemented PhpStan at level 10
- Keep a discovery cache in the process, so a test run scans once

### Performance

- Only instantiate commands if necessary
- Add memory cache to speed up large test runs

### Refactoring

- Move to mono-repo structure


