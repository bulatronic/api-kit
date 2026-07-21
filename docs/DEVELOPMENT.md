# Development Guide

This guide helps you set up a development environment for contributing to ApiKit Bundle.

## Prerequisites

- Docker and Docker Compose
- PHP 8.2+ (for local development without Docker)
- Composer
- Git

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/bulatronic/api-kit.git
cd api-kit
```

### 2. Install Dependencies

```bash
composer install
```

Symfony Flex may prompt to run recipes for newly added dev dependencies (e.g.
`nelmio/api-doc-bundle`, `symfony/property-info`). The dev sandbox (`tests/app/Kernel.php`) is a
minimal `MicroKernelTrait` kernel with no `config/bundles.php` — decline (`n`, the default)
any **contrib** recipe prompt (matches `"allow-contrib": false` in `composer.json`); official
recipes apply automatically without asking. None of the OpenAPI attribute tests need
`nelmio/api-doc-bundle`'s bundle registered — see [Testing OpenAPI Attributes](#4-testing-openapi-attributes)
below.

### 3. Start Development Environment

The project uses FrankenPHP for development:

```bash
docker compose up -d
```

### 4. Enter the Container

```bash
docker compose exec frankenphp bash
```

## Development Workflow

### Running Tests

```bash
# Inside the container
composer test

# Or outside
docker compose exec frankenphp composer test
```

### Static Analysis

```bash
# PHPStan level 8
composer phpstan
```

### Code Style

```bash
# Check code style
composer cs-check

# Fix code style automatically
composer cs-fix
```

### All Quality Checks

```bash
# Run everything at once
composer test && composer phpstan && composer cs-check
```

## Project Structure

```
api-kit/
├── bin/                           # Executable scripts
├── config/                        # Bundle configuration
│   ├── packages/                  # Package configs
│   ├── routes/                    # Route definitions
│   └── reference.php              # Auto-generated (apps only, do not hand-edit)
├── docs/                          # Developer / project-conventions documentation
│   ├── ARCHITECTURE.md
│   ├── CONTROLLER-CONVENTIONS.md  # for AI agents/humans consuming ApiKit
│   ├── DEVELOPMENT.md             # this file
│   ├── EXAMPLES.md
│   └── OPENAPI.md
├── public/                        # Public web root
├── src/                           # Source code
│   ├── Controller/                # AbstractApiController / ApiControllerTrait
│   ├── DependencyInjection/       # Extension, Configuration, compiler passes
│   ├── EventListener/             # ExceptionListener
│   ├── Exception/                 # ApiException
│   ├── OpenApi/                   # Optional: ApiSuccessResponse/ApiErrorResponse/... attributes
│   │   ├── Attribute/             #   + EnvelopeSchemas — require-dev/suggest only, see OPENAPI.md
│   │   └── Schema/
│   ├── Resources/config/          # services.yaml (bundle service definitions)
│   ├── Response/                  # ResponseFactory(Interface)
│   ├── Validator/                 # EntityExists constraint + validator
│   └── ApiKitBundle.php
├── tests/                         # Test suite
│   ├── app/                       # Minimal MicroKernelTrait kernel for the dev sandbox
│   ├── Fixture/                   # Scan-target-only classes, never run as tests themselves
│   ├── Support/                   # Shared test base classes (e.g. OpenApiTestCase)
│   └── Unit/                      # Mirrors src/ 1:1
├── .editorconfig                  # Editor configuration
├── .gitignore                     # Git ignore rules
├── .php-cs-fixer.dist.php         # PHP CS Fixer config
├── AGENTS.md                      # Instructions for AI agents working on ApiKit itself
├── composer.json                  # Composer dependencies
├── Dockerfile                     # Docker image
├── docker-compose.yml             # Docker compose config
├── phpstan.dist.neon              # PHPStan configuration
└── phpunit.dist.xml               # PHPUnit configuration
```

## Coding Standards

### 1. PHP Version

- Minimum: PHP 8.2
- Use modern PHP features:
  - Readonly classes and properties
  - Property hooks (asymmetric visibility)
  - Named arguments
  - Match expressions
  - Attributes

### 2. Type Safety

```php
// ✅ Always declare strict types
declare(strict_types=1);

// ✅ Use full type hints
public function method(string $param): ?int

// ✅ Use readonly for immutable objects
final readonly class MyClass

// ❌ Avoid mixed types
public function method(mixed $param)
```

### 3. Naming Conventions

- Classes: PascalCase
- Methods: camelCase
- Properties: camelCase
- Constants: UPPER_SNAKE_CASE
- Attributes: PascalCase

### 4. Documentation

```php
/**
 * Brief description.
 *
 * Extended description if needed.
 *
 * @param string $param Parameter description
 * @return JsonResponse Response description
 */
public function method(string $param): JsonResponse
```

### 5. Code Style

Follow PSR-12 with these additions:

```php
// ✅ Use final classes by default
final class MyClass

// ✅ Use readonly where possible
final readonly class ImmutableClass

// ✅ Use named arguments for clarity
$this->factory->success(
    data: $data,
    statusCode: 200,
    meta: ['timestamp' => time()],
);

// ✅ Use match over switch
$result = match ($type) {
    'json' => $this->json(),
    'xml' => $this->xml(),
    default => throw new \InvalidArgumentException(),
};
```

## Testing Guidelines

### 1. Test Structure

```php
<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;

final class ResponseFactoryTest extends TestCase
{
    public function testSuccessCreatesSuccessResponse(): void
    {
        // Arrange
        $factory = new ResponseFactory();
        
        // Act
        $response = $factory->success(['key' => 'value']);
        
        // Assert
        $this->assertSame(200, $response->getStatusCode());
        // More assertions...
    }
}
```

### 2. Test Coverage

- Aim for 80%+ code coverage
- Test all public methods
- Test edge cases and error conditions
- Test configuration options

### 3. Test Naming

```php
// ✅ Descriptive test names
public function testSuccessResponseIncludesTimestampWhenConfigured(): void

// ❌ Vague test names
public function testSuccess(): void
```

### 4. Testing OpenAPI Attributes

`src/OpenApi/Attribute/*` are plain `zircote/swagger-php` attribute classes, tested without a
Symfony kernel or `nelmio/api-doc-bundle`'s own DI container. Two gotchas worth knowing before
touching this area:

- **No static `Generator::scan()`.** Some swagger-php versions/docs mention it, but it doesn't
  exist in the version this project pins (`^6.0`). Use `(new \OpenApi\Generator())->generate([...])`
  — see `tests/Support/OpenApiTestCase::scanFixtures()`. Always check the actually-installed API
  in `vendor/zircote/swagger-php/src/Generator.php` before assuming a method signature.
- **`Nelmio\ApiDocBundle\Attribute\Model` only resolves inside Nelmio's own pipeline.** A bare
  `Generator::generate()` scan (no Symfony container, no `ModelRegister`) cannot turn a `#[Model]`
  ref into a `$ref` string and throws. `ApiSuccessResponse`/`ApiCreatedResponse` use `#[Model]`
  for `data`, so their tests construct the attribute directly and assert on its own object graph
  (`$response->_unmerged[0]->properties[...]`) instead of running a full document scan — see
  `ApiSuccessResponseTest`. `tests/Fixture/OpenApi/Controller/WidgetFixtureController.php`
  deliberately avoids `#[ApiSuccessResponse]`/`#[ApiCreatedResponse]` for this exact reason: any
  method in a scanned directory using `#[Model]` crashes the *entire* scan, including unrelated
  fixtures.

## Git Workflow

### 1. Branch Naming

- Features: `feature/short-description`
- Bugfixes: `fix/short-description`
- Documentation: `docs/short-description`

### 2. Commit Messages

Follow conventional commits:

```
feat: add EntityExists validator
fix: correct timestamp format in responses
docs: update ARCHITECTURE.md
test: add tests for ResponseFactory
refactor: simplify exception handling
```

### 3. Pull Requests

1. Create a feature branch
2. Make your changes
3. Add/update tests
4. Run quality checks
5. Update documentation if needed
6. Create PR with clear description

## Common Tasks

### Adding a New Feature

1. **Design First**
   - Does it fit the minimalist philosophy?
   - Is it a core feature or an extension?
   - Check existing issues/discussions

2. **Implement**
   - Write tests first (TDD)
   - Implement feature
   - Update documentation

3. **Quality Checks**
   ```bash
   composer test
   composer phpstan
   composer cs-fix
   ```

4. **Documentation**
   - Update README.md if needed
   - Add examples to EXAMPLES.md
   - If the feature changes a convention an AI agent (or a new contributor) should follow,
     update AGENTS.md (for ApiKit's own repo) or CONTROLLER-CONVENTIONS.md (for projects
     consuming ApiKit)
   - Update CHANGELOG.md

### Fixing a Bug

1. **Reproduce**
   - Write a failing test
   - Verify the bug exists

2. **Fix**
   - Implement the fix
   - Ensure test passes

3. **Verify**
   - Run full test suite
   - Check related functionality

### Updating Documentation

- Keep examples up-to-date
- Use real, working code
- Test all examples
- Check spelling and grammar

## Debugging

### Using Xdebug (if configured)

```bash
# Set breakpoint in code
xdebug_break();

# Or use your IDE's debugging tools
```

### Logging

```bash
# View container logs
docker compose logs -f frankenphp

# View Symfony logs
tail -f var/log/dev.log
```

### Profiling

```bash
# Use Blackfire (if configured)
blackfire run php bin/console

# Or Symfony profiler in browser
http://localhost:8000/_profiler
```

## Environment Variables

Create `.env.local` for local development:

```env
APP_ENV=dev
APP_DEBUG=true
```

## Troubleshooting

### Tests Fail

```bash
# Clear cache
php bin/console cache:clear

# Reinstall dependencies
rm -rf vendor
composer install
```

### Container Issues

```bash
# Rebuild containers
docker compose down
docker compose build --no-cache
docker compose up -d
```

### PHPStan Errors

```bash
# Generate baseline (use sparingly)
composer phpstan -- --generate-baseline

# Clear result cache
rm -rf var/cache/phpstan
```

## Performance Testing

### Benchmarking

```bash
# Use Apache Bench
ab -n 1000 -c 10 http://localhost:8000/api/test

# Or hey
hey -n 1000 -c 10 http://localhost:8000/api/test
```

### Memory Profiling

```php
// Add at start of request
$start = memory_get_usage();

// Add at end of request
$end = memory_get_usage();
echo "Memory used: " . ($end - $start) . " bytes\n";
```

## Release Process

1. Update CHANGELOG.md
2. Update version in composer.json (if needed)
3. Run all quality checks
4. Create git tag: `v1.0.0`
5. Push tag: `git push origin v1.0.0`
6. Create GitHub release

## Getting Help

- Read existing documentation
- Check open/closed issues
- Ask in discussions
- Contact maintainer: bulat.coder@gmail.com

## Code of Conduct

- Be respectful and constructive
- Focus on the code, not the person
- Help others learn and grow
- Follow the project's goals and philosophy
