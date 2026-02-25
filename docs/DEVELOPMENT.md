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
├── bin/                    # Executable scripts
├── config/                 # Bundle configuration
│   ├── packages/          # Package configs
│   ├── routes/            # Route definitions
│   └── services.yaml      # Service definitions
├── docs/                   # Developer documentation
├── public/                 # Public web root
├── src/                    # Source code
│   ├── Controller/        # Traits for controllers
│   ├── DependencyInjection/ # Configuration
│   ├── EventListener/     # Event listeners
│   ├── Response/          # Response factory
│   ├── Validator/         # Custom validators
│   └── ApiKitBundle.php
├── tests/                  # Test suite
│   ├── Unit/             # Unit tests
│   └── Functional/       # Functional tests
├── .editorconfig          # Editor configuration
├── .gitignore            # Git ignore rules
├── .php-cs-fixer.dist.php # PHP CS Fixer config
├── composer.json          # Composer dependencies
├── Dockerfile            # Docker image
├── docker-compose.yml    # Docker compose config
├── phpstan.dist.neon     # PHPStan configuration
└── phpunit.dist.xml      # PHPUnit configuration
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
