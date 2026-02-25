# Contributing to ApiKit Bundle

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Ways to Contribute

- Report bugs and issues
- Suggest new features or improvements
- Submit pull requests
- Improve documentation
- Help others in discussions

## Before You Start

1. **Read the Documentation**
   - [README.md](README.md)
   - [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
   - [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md)

2. **Check Existing Issues**
   - Search for similar issues or feature requests
   - Comment on existing issues before starting work

3. **Understand the Philosophy**
   - Minimalist approach
   - Foundation, not framework
   - Focus on core features only

## Reporting Bugs

### Before Reporting

- Check if the bug has already been reported
- Test with the latest version
- Verify it's not a configuration issue

### Bug Report Template

```markdown
**Description**
Clear description of the bug

**Steps to Reproduce**
1. Step one
2. Step two
3. ...

**Expected Behavior**
What you expected to happen

**Actual Behavior**
What actually happened

**Environment**
- PHP Version: 8.2+
- Symfony Version: 7.4.x
- Bundle Version: 0.1.0
- OS: Linux/Mac/Windows

**Additional Context**
Stack traces, logs, screenshots, etc.
```

## Suggesting Features

### Feature Request Template

```markdown
**Problem**
What problem does this solve?

**Proposed Solution**
How should it work?

**Alternatives Considered**
What other approaches did you consider?

**Implementation Ideas**
Any thoughts on implementation?
```

### Feature Guidelines

✅ **Good Fit:**
- Core API response handling
- Essential validation
- Common error handling patterns
- Improves developer experience
- Maintains simplicity

❌ **Not a Good Fit:**
- High-level features (pagination, filtering)
- Framework-specific solutions
- Complex abstractions
- Features with heavy dependencies

## Pull Request Process

### 1. Setup Development Environment

See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for details.

### 2. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/bug-description
```

### 3. Make Your Changes

- Write clean, well-documented code
- Follow coding standards
- Add/update tests
- Update documentation

### 4. Run Quality Checks

```bash
# Run all checks
composer test
composer phpstan
composer cs-fix
```

All must pass before submitting.

### 5. Commit Your Changes

Use conventional commit format:

```bash
git commit -m "feat: add new feature"
git commit -m "fix: resolve bug in ResponseFactory"
git commit -m "docs: update README with new examples"
git commit -m "test: add tests for ExceptionListener"
```

### 6. Push and Create Pull Request

```bash
git push origin feature/your-feature-name
```

Then create a PR on GitHub.

## Pull Request Guidelines

### PR Title

Use conventional commit format:

- `feat: Add EntityExists validator`
- `fix: Correct timestamp format`
- `docs: Update ARCHITECTURE.md`
- `refactor: Simplify exception handling`
- `test: Add ResponseFactory tests`

### PR Description Template

```markdown
## Description
Brief description of changes

## Motivation
Why is this change needed?

## Changes
- Change 1
- Change 2
- ...

## Testing
How was this tested?

## Checklist
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] All quality checks pass
- [ ] Backward compatible (or breaking change documented)
```

### PR Review Process

1. **Automated Checks**
   - Tests must pass
   - PHPStan must pass
   - Code style must be correct

2. **Manual Review**
   - Code quality and style
   - Test coverage
   - Documentation completeness
   - Backward compatibility

3. **Feedback**
   - Address reviewer comments
   - Update PR as needed
   - Request re-review

4. **Merge**
   - Approved PRs will be merged
   - Delete your branch after merge

## Coding Standards

### PHP Style

Follow PSR-12 and project conventions:

```php
<?php

declare(strict_types=1);

namespace ApiKit\YourNamespace;

/**
 * Class description.
 */
final readonly class YourClass
{
    public function __construct(
        private SomeService $service,
    ) {
    }

    /**
     * Method description.
     *
     * @param string $param Parameter description
     * @return mixed Return description
     */
    public function yourMethod(string $param): mixed
    {
        // Implementation
    }
}
```

### Best Practices

✅ **Do:**
- Use strict types
- Use readonly for immutable objects
- Use final classes by default
- Use named arguments
- Write descriptive names
- Add type hints everywhere
- Write clear documentation

❌ **Don't:**
- Use mixed types unless necessary
- Leave unused imports
- Write complex, nested logic
- Skip documentation
- Ignore warnings/errors
- Use deprecated features

## Testing Requirements

### Test Coverage

- Aim for 80%+ coverage
- Test all public methods
- Test edge cases
- Test error conditions

### Test Structure

```php
final class YourClassTest extends TestCase
{
    private YourClass $subject;

    protected function setUp(): void
    {
        $this->subject = new YourClass();
    }

    public function testMethodDoesWhatItShould(): void
    {
        // Arrange
        $input = 'test';

        // Act
        $result = $this->subject->method($input);

        // Assert
        $this->assertSame('expected', $result);
    }
}
```

### Test Naming

- Use descriptive names
- Format: `testMethodNameUnderConditionExpectedResult`
- Examples:
  - `testSuccessReturns200StatusCode`
  - `testErrorWithInvalidDataReturnsValidationError`
  - `testNoContentReturns204WithEmptyBody`

## Documentation Guidelines

### Code Documentation

```php
/**
 * Brief one-line description.
 *
 * Extended description if needed. Explain the why,
 * not just the what. Include usage examples if helpful.
 *
 * @param string $param Description of parameter
 * @param int $other Description of other parameter
 * @return JsonResponse Description of return value
 * @throws \Exception When and why this is thrown
 */
public function method(string $param, int $other): JsonResponse
```

### README Updates

- Keep examples up-to-date
- Add new features to feature list
- Update installation instructions if needed
- Keep TOC in sync

## Backward Compatibility

### Breaking Changes

- Avoid if possible
- Document clearly
- Provide migration guide
- Deprecate first when feasible

### BC Promise

We follow semantic versioning:
- MAJOR: Breaking changes
- MINOR: New features, backward compatible
- PATCH: Bug fixes, backward compatible

### Deprecation Process

1. Mark as deprecated with `@deprecated` tag
2. Add deprecation notice in CHANGELOG
3. Provide alternative solution
4. Keep deprecated code for at least one major version
5. Remove in next major version

Example:

```php
/**
 * @deprecated Since 1.2.0, use newMethod() instead. Will be removed in 2.0.0.
 */
public function oldMethod(): void
{
    trigger_deprecation(
        'ApiKit/symfony-bundle',
        '1.2.0',
        'Method %s is deprecated, use newMethod() instead.',
        __METHOD__
    );
    
    $this->newMethod();
}
```

## Community Guidelines

### Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn
- Give credit where due
- Assume good intentions

### Communication

- Use clear, professional language
- Be patient with beginners
- Provide examples when explaining
- Link to relevant documentation
- Ask for clarification if needed

### Getting Help

- Read documentation first
- Search existing issues
- Provide minimal reproducible examples
- Include relevant context
- Be responsive to follow-up questions

## Review Process

### What Reviewers Look For

1. **Functionality**
   - Does it work as intended?
   - Are edge cases handled?
   - Are there bugs?

2. **Code Quality**
   - Is it readable and maintainable?
   - Does it follow standards?
   - Is it properly documented?

3. **Tests**
   - Are there sufficient tests?
   - Do tests pass?
   - Is coverage adequate?

4. **Documentation**
   - Is it documented clearly?
   - Are examples provided?

5. **Compatibility**
   - Is it backward compatible?
   - Are breaking changes documented?
   - Does it work with supported versions?

### Responding to Reviews

- Thank reviewers for their time
- Address all comments
- Ask questions if unclear
- Make requested changes
- Update PR description if needed
- Request re-review when ready

## Release Process

For maintainers:

1. Create git tag: `git tag -a v1.0.0 -m "Release 1.0.0"`
2. Push tag: `git push origin v1.0.0`
3. Create GitHub release with a summary of changes

## Questions?

- Open a discussion on GitHub
- Check existing issues and PRs
- Read documentation
- Contact maintainer: bulat.coder@gmail.com

## Thank You!

Your contributions help make ApiKit better for everyone. We appreciate your time and effort!
