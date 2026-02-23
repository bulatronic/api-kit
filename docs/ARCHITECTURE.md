# Architecture

Architecture and design decisions of the ApiKit Bundle.

## Overview

ApiKit is a minimalist Symfony Bundle that provides essential components for building REST APIs. The philosophy is to standardize API responses and automate common patterns without being opinionated about business logic.

## Core Principles

### 1. Foundation, Not Framework

- Only core components — no pagination, filtering, authentication
- Easy to extend or replace any part
- Minimal required dependencies

### 2. Convention Over Configuration

- Sensible defaults (no config needed to get started)
- Optional config file for fine-tuning
- Automatic service registration

### 3. Symfony Native

- Leverages Symfony's event system, validator, and DI
- Does not duplicate what Symfony already provides
- Follows Symfony best practices

---

## Components

### `ResponseFactory`

**Purpose:** Create standardized JSON responses.

**Response format:**

```json
// Success
{
    "success": true,
    "data": mixed,
    "meta": {"timestamp": "ISO8601", ...}
}

// Error
{
    "success": false,
    "error": {
        "code": "SNAKE_CASE_CODE",
        "message": "Human-readable message",
        "details": {}
    }
}
```

**Why this format?**
- `success` flag makes client-side parsing trivial
- `code` is machine-readable (for i18n, frontend logic)
- `details` is optional structured payload (violations, business context)
- `meta` carries cross-cutting concerns like pagination or timestamp

---

### `AbstractApiController` and `ApiControllerTrait`

**Purpose:** Provide convenience `respond*` methods in controllers.

**Two options for the same functionality:**

| | `AbstractApiController` | `ApiControllerTrait` |
|---|---|---|
| Usage | `extends AbstractApiController` | `use ApiControllerTrait` |
| When | No other base class | Already extends something |
| How it works | Extends `AbstractController` + includes trait | Includes trait directly |

**DI injection:** `ApiControllerTrait` exposes a `#[Required]` setter `setResponseFactory()`.
Symfony autowiring calls it automatically — no constructor boilerplate.

---

### `ApiException`

**Purpose:** Throw structured HTTP errors with domain-specific details from anywhere in the codebase.

```php
throw new ApiException(409, 'Slug already taken', [
    'field' => 'slug',
    'value' => $dto->slug,
]);
```

**Why?** Symfony's `HttpException` only carries a string message. `ApiException` adds
`getDetails(): array` which `ExceptionListener` automatically includes in the response.
This lets services communicate rich error context without depending on `ResponseFactory`.

---

### `ExceptionListener`

**Purpose:** Catch all exceptions and convert them to standardized JSON responses. Controllers never need `try/catch`.

**Exception routing:**

```
\Throwable
├── HttpExceptionInterface
│   ├── previous: ValidationFailedException  → 422 with violations
│   ├── ApiException                          → status + getDetails()
│   └── other HttpException                  → status + default message
├── ValidationFailedException (direct)       → 422 with violations
└── Any other \Throwable                     → 500 Internal Server Error
```

**Logging:** Only 5xx responses are logged at `error` level. 4xx are client errors — logging them would pollute production logs.

---

### `EntityExists` Validator

**Purpose:** Validate entity existence in database directly in DTOs.

**Optional dependency:** Only registered when `doctrine/orm` is installed.

```php
#[EntityExists(User::class)]           // search by id (default)
#[EntityExists(Category::class, field: 'slug')]  // search by any field
```

**Why in DTO?** Keeps validation logic in one place. The service receives a DTO that is already guaranteed valid — no defensive checks needed.

---

## Configuration

```yaml
api_kit:
    response:
        include_timestamp: true        # Add timestamp to all success responses
        pretty_print: '%kernel.debug%' # JSON_PRETTY_PRINT in dev

    exception_handling:
        log_errors: true               # Log 5xx errors
        show_trace: '%kernel.debug%'   # Include stack trace in 500 response body
```

All options have sensible defaults — the bundle works without any config file.

---

## Extending the Bundle

### Custom response methods

```php
final class AppResponseFactory extends ResponseFactory
{
    public function paginated(array $items, int $total, int $page, int $perPage): JsonResponse
    {
        return $this->success($items, meta: ['pagination' => [...]]);
    }
}
```

### Custom exception listener (higher priority)

```php
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class DomainExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof MyDomainException) {
            return; // let ApiKit's listener handle the rest
        }
        // ...
    }
}
```

---

## Design Patterns

| Pattern | Where used |
|---------|-----------|
| Factory | `ResponseFactory` — creates `JsonResponse` objects |
| Trait / Mixin | `ApiControllerTrait` — adds behavior without inheritance |
| Template Method | `AbstractApiController` — base class using the trait |
| Event Listener | `ExceptionListener` — reacts to Symfony's kernel exception event |
| Value Object | `ApiException` — carries error code + details as a unit |
| Constraint Pattern | `EntityExists` — follows Symfony validator convention |

---

## What's Not Included (by design)

- **Pagination** — too project-specific (cursor, offset, keyset)
- **Filtering / Sorting** — use Doctrine criteria or a dedicated library
- **Authentication** — use `symfony/security-bundle`
- **Authorization** — use Symfony voters
- **API versioning** — use route prefixes or `Accept` header negotiation
- **OpenAPI / Swagger** — use `nelmio/api-doc-bundle`
- **HATEOAS** — use `willdurand/hateoas-bundle`

---

## Comparison

| | ApiKit | API Platform | FOSRestBundle |
|---|---|---|---|
| Response format | Fixed, simple | Hydra/JSON-LD | Configurable |
| Validation | Symfony native | Symfony native | Symfony native |
| Exception handling | Automatic | Automatic | Manual |
| Serialization | Manual (pass array/object) | Automatic | Automatic |
| Scope | Minimal foundation | Full framework | Comprehensive |
| Opinionated | Low | High | Medium |
