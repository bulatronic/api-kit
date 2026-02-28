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

### `ResponseFactoryInterface` and `ResponseFactory`

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

**Extension points:**

`ResponseFactoryInterface` is the declared contract. `ResponseFactory` is the default implementation. `ExceptionListener` and `ApiControllerTrait` both depend on the interface, so replacing the factory replaces the format everywhere — including exception handling.

Two extension strategies:

| Goal | Approach |
|------|----------|
| Add methods (`paginated()`, `accepted()`, …) | Extend `ResponseFactory` |
| Replace the format entirely | Implement `ResponseFactoryInterface` |

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

**Architecture:** The validator is split into three parts to keep the bundle decoupled from Doctrine:

| Class | Role |
|-------|------|
| `EntityExistenceCheckerInterface` | Port — defines the `exists()` contract, no Doctrine dependency |
| `DoctrineEntityExistenceChecker` | Adapter — wraps `EntityManagerInterface`, auto-registered when doctrine/orm is installed |
| `EntityExistsValidator` | Constraint validator — depends only on the interface, Doctrine-agnostic |

`ApiKitExtension` registers `DoctrineEntityExistenceChecker` and binds it to `EntityExistenceCheckerInterface` only when `interface_exists('Doctrine\ORM\EntityManagerInterface')` returns true. The check uses a string literal to avoid a static class reference that would trigger PHPStan "Undefined class" errors in projects without Doctrine.

**Custom persistence backend:** If your project uses a different ORM, an HTTP API, or any other data source, register your own implementation:

```php
// In your bundle's Extension or services.yaml
$container->setAlias(EntityExistenceCheckerInterface::class, YourCustomChecker::class);
```

---

### File Uploads

**`#[MapRequestPayload]` does not handle file uploads** — it deserializes JSON/form-encoded bodies only. For files, use Symfony's `#[MapUploadedFile]` attribute (available since Symfony 7.1).

The `ExceptionListener` already handles `HttpException` thrown by `#[MapUploadedFile]` when validation fails (wrong MIME type, size exceeded, dimension constraints), so no bundle changes are needed — it works out of the box.

`#[MapRequestPayload]` and `#[MapUploadedFile]` can be combined in the same action for mixed multipart requests (JSON fields + file):

```php
public function create(
    #[MapRequestPayload] CreatePostDto $dto,
    #[MapUploadedFile([new Assert\Image(...)])]
    ?UploadedFile $thumbnail = null,
): JsonResponse { ... }
```

> **Note:** `Assert\Video` requires Symfony 7.4+ and FFmpeg/ffprobe installed on the server. Using `#[MapUploadedFile]` with `PATCH` had a known bug in earlier Symfony 7.x versions — prefer `POST` for file upload endpoints; the issue was resolved in Symfony 7.4.

See [examples.md — File Uploads](examples.md#file-uploads) for complete working examples (avatar upload, video upload, `FileUploader` service).

---

## Configuration

```yaml
api_kit:
    response:
        include_timestamp: true        # Add timestamp to all success responses
        pretty_print: '%kernel.debug%' # JSON_PRETTY_PRINT in dev

    exception_handling:
        log_errors: true               # Log 5xx errors
        show_trace: '%kernel.debug%'   # Include stack trace in 5xx error responses (true in dev, false in prod)
```

All options have sensible defaults — the bundle works without any config file.

---

## Extending the Bundle

### Add response methods (extend)

Extend `ResponseFactory` to add project-specific methods while keeping the default format:

```php
// src/Api/AppResponseFactory.php
readonly class AppResponseFactory extends ResponseFactory
{
    public function paginated(array $items, int $total, int $page, int $perPage): JsonResponse
    {
        return $this->success($items, meta: [
            'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage],
        ]);
    }
}
```

```yaml
# config/services.yaml
ApiKit\Response\ResponseFactory:
    class: App\Api\AppResponseFactory
```

### Replace the response format (implement)

Implement `ResponseFactoryInterface` to use a completely different response structure.
`ExceptionListener` will automatically use your format for all error responses too:

```php
// src/Api/MyResponseFactory.php
final readonly class MyResponseFactory implements ResponseFactoryInterface
{
    public function success(mixed $data = null, int $statusCode = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse(['ok' => true, 'result' => $data] + ($meta ? ['meta' => $meta] : []), $statusCode);
    }

    public function error(string $message, string $code = 'ERROR', int $statusCode = 400, array $details = []): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'error' => $code, 'message' => $message], $statusCode);
    }

    public function created(mixed $data, array $meta = []): JsonResponse
    {
        return $this->success($data, 201, $meta);
    }

    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }
}
```

```yaml
# config/services.yaml
ApiKit\Response\ResponseFactoryInterface:
    class: App\Api\MyResponseFactory
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
| Interface / Port | `ResponseFactoryInterface`, `EntityExistenceCheckerInterface` — declared extension points |
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
