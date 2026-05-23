# Contributing

Thanks for your interest in contributing! This is a small library, so the process is light.

## Reporting bugs

Open an issue at <https://github.com/NielsJanssen/laravel-discovery/issues>. The more reproducible, the better:

- PHP and Laravel versions.
- The version of `laravel-discovery` you're on.
- A minimal code sample that triggers the bug. Bonus points for a failing test against the workbench app.
- What you expected vs. what happened.

If you found a security issue, please **don't** open a public issue. Email the maintainer (see `composer.json`
`authors`) directly.

## Feature requests

Open an issue describing:

- The problem you're trying to solve (not the solution you've already designed).
- Why you think it belongs in this package vs. in your own application code.
- A rough API sketch if you have one. Code is easier to react to than prose.

The package's goal is to bring Tempest Discovery to Laravel with batteries included for Laravel's core surfaces (
commands, events, routes, schedule). Features that go beyond that are welcome too, but the bar is whether they'd
genuinely help a typical Laravel app.

## Sending a pull request

1. Fork the repo and clone your fork.
2. Create a branch off `main`:
   ```bash
   git switch -c feature/your-thing main
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Make your change. Keep commits focused: one logical change per commit. The CHANGELOG is generated from commit
   messages via `git-cliff`, so meaningful messages help.
5. Run the lint and test suites locally (see below).
6. Push and open a PR. Describe what you changed and why; link any related issue.

We use [conventional commits](https://www.conventionalcommits.org/) loosely: `feat:`, `fix:`, `refactor:`, `test:`,
`docs:`, `chore:`. Not strict, but it makes the changelog cleaner.

## Running tests

```bash
composer test
```

Single file or filter:

```bash
vendor/bin/pest tests/Feature/Feature/Command/CommandDiscoveryTest.php
vendor/bin/pest tests/Feature/Feature/Command/CommandDiscoveryTest.php --filter=registers_commands
```

CI mode (parallel + coverage):

```bash
vendor/bin/pest --ci
```

The test suite uses an embedded "workbench" Laravel app at `workbench/`. If you're adding a new discoverable kind of
class, drop a fixture there to exercise it. See existing tests under `tests/Feature/` for the pattern.

## Linting

```bash
composer lint
```

This runs Laravel Pint. Fixes are applied in place; review the diff before committing.

## Adding a new discovery component

If you're adding a new piece of Laravel that should be discoverable (e.g., broadcast channels, queues, gates), aim for
the existing structure:

```
src/<Component>/
  <Component>Discovery.php   # implements Discovery, uses IsDiscovery
  Attribute.php              # the user-facing attribute(s)
  Discovered<Thing>.php      # cacheable DTO if needed
```

Things that have helped on existing components:

- Mark singleton-friendly discoveries with `#[Singleton]`. The discovery is resolved once per request, but only
  meaningfully constructed when Tempest needs it.
- Guard `apply()` against framework-level caching (e.g., `routesAreCached()`).
- Store cacheable types in `discoveryItems` (strings, scalars, simple DTOs). If you need reflection or closures at
  apply-time, re-derive them from live attributes.
- Add tests for the discovery itself **and** for at least one integration scenario (the discovered thing actually being
  invoked).

## Documentation

If your change is user-visible, please update the relevant doc:

- New attribute or option? Update the matching `docs/<component>.md`.
- New configuration key? Update `docs/installation.md`.
- New discovery class shipped with the package? Update `README.md`'s feature list and add a matching page under `docs/`.

The docs are written to be approachable for Laravel developers who haven't seen Tempest before; keep the tone friendly
and the examples real. Avoid contrived `Foo`/`Bar` snippets where a more realistic one would do.

## Release process

Releases are automated. Tagging a version triggers the release workflow (`.github/workflows/release.yml`), which uses
`git-cliff` to generate the changelog from commit history. Maintainers handle this; contributors don't need to touch the
changelog or version themselves.

## Code style

- All PHP files start with `declare(strict_types=1);`.
- PSR-4 under `NielsJanssen\Laravel\Discovery\`.
- Run `composer lint` before committing.
- Match the style of the surrounding code. The codebase favors final classes, readonly properties, and named arguments
  at call sites; feel free to follow suit.

## Questions

If anything in this document is unclear, please open an issue or start a discussion.
