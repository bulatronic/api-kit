# AGENTS.md

Instructions for AI coding agents working **inside this repository**
(`bulatronic/api-kit` — a Symfony bundle, not an application).

If you're building an API *with* ApiKit in a downstream project, this file is not for you —
see [docs/CONTROLLER-CONVENTIONS.md](docs/CONTROLLER-CONVENTIONS.md) instead, it's written to
be copied into *your* project's agent-instructions file.

## Project overview

PHP 8.2+ library for Symfony 7.4/8.0, `type: symfony-bundle`. No application, no entrypoint.
PSR-4: `ApiKit\` → `src/`, `ApiKit\Tests\` → `tests/`. The core has **zero hard dependency**
on Doctrine, `nelmio/api-doc-bundle`, or `zircote/swagger-php` — anything optional lives behind
`require-dev`/`suggest` and either isn't referenced from the always-loaded
`ApiKitBundle`/`ApiKitExtension`, or is guarded by `interface_exists()`/`class_exists()` (see
`ApiKitExtension::load()` for the exact pattern used for Doctrine).

## Commands — always run inside the app container, never on the host

```bash
docker compose exec frankenphp bash
```

Then, from `/app`:

```bash
composer install
composer test        # PHPUnit — tests/
composer phpstan     # level 8, src/ + tests/
composer cs-check    # php-cs-fixer --dry-run --diff
composer cs-fix
```

Single test: `vendor/bin/phpunit --filter testMethodName tests/Path/To/FileTest.php`.

## Code style

- `declare(strict_types=1)` in every file; namespace mirrors the file path exactly.
- `final` classes by default; only drop `final` when a class is deliberately designed to be
  extended (e.g. `ApiSuccessResponse` → `ApiCreatedResponse`).
- Constructor property promotion, `readonly` where the value never changes after construction.
- No business logic in this bundle — it's a thin HTTP-layer helper. Check
  [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) ("What's Not Included") before adding anything
  that looks like pagination, auth, filtering, or versioning logic — those are explicitly out
  of scope.

## Testing conventions

- One test class per source class: `tests/Unit/<mirrors src/ path>/<Class>Test.php`.
- Fixture-only classes (never run as tests themselves) live under `tests/Fixture/...` — see
  `tests/Fixture/OpenApi/` for the pattern (a fixture DTO + a "controller" that's really just a
  scan target for swagger-php's `Generator`).
- OpenAPI attribute tests assert on the JSON from `(new OpenApi\Generator())->generate([...])`.
  Do not assume a static `Generator::scan()` shortcut exists — it doesn't, in the swagger-php
  version this project pins (^6.0). Whenever you touch anything under `src/OpenApi/` or its
  tests, check the actually-installed API in `vendor/zircote/swagger-php/src/Generator.php`
  rather than relying on memory or documentation for a different version.

## Boundaries

- **Never** add `nelmio/api-doc-bundle`, `zircote/swagger-php`, or `doctrine/orm` to
  `require` — `require-dev`/`suggest` only. The core bundle must install and work with zero
  optional dependencies present.
- **Never** add a processor/listener that automatically wraps arbitrary responses/DTOs into
  the envelope. `ApiKit\OpenApi\Attribute\*` are explicit and opt-in, one attribute per
  response — this is a deliberate, previously-rejected design (see
  [docs/OPENAPI.md](docs/OPENAPI.md), "What this deliberately does not do").
- **Never** reference `ApiKit\OpenApi\*` or Doctrine classes from `ApiKitBundle`/
  `ApiKitExtension` without a `class_exists()`/`interface_exists()` guard first — a bare `use`
  statement would break autoloading for projects that don't have those packages installed.
- **Ask first** before renaming/restructuring anything under `docs/` — the file names there are
  a deliberate, human-readable "project conventions" surface that downstream consumers link to
  and copy from; treat existing filenames as a stable public interface.
- **Ask first** before touching `CHANGELOG.md` or cutting a version tag — releases are manual.
