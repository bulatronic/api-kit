# ApiKit Symfony Bundle

[![PHP Version](https://img.shields.io/badge/php-8.5%2B-blue.svg)](https://php.net)
[![Symfony Version](https://img.shields.io/badge/symfony-7.4-green.svg)](https://symfony.com)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A minimalist Symfony Bundle for building REST APIs with standardized responses, automatic exception handling, and DTO validation.

## Key Features

- **Standardized JSON responses** — unified format for all endpoints
- **Automatic exception handling** — exceptions become JSON without `try/catch` in controllers
- **`ApiException`** — throw structured errors with details from anywhere in the codebase
- **DTO validation** — uses native Symfony `#[MapRequestPayload]` / `#[MapQueryString]`
- **`EntityExists` validator** — check entity existence directly in DTO
- **`AbstractApiController`** + **`ApiControllerTrait`** — convenient response helpers
- **PHP 8.5 + Symfony 7.4** — modern features, minimal dependencies

## Before & After

**Without ApiKit** — boilerplate in every controller:

```php
public function create(Request $request): JsonResponse
{
    try {
        $dto = $this->serializer->deserialize($request->getContent(), CreatePostDto::class, 'json');
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 422);
        }
        $result = $this->service->create($dto);
        return $this->json(['success' => true, 'data' => $result], 201);
    } catch (ConflictException $e) {
        return $this->json(['error' => $e->getMessage()], 409);
    } catch (\Throwable $e) {
        $this->logger->error($e->getMessage());
        return $this->json(['error' => 'Internal error'], 500);
    }
}
```

**With ApiKit** — one line, same result:

```php
public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
{
    return $this->respondCreated($this->service->create($dto));
}
```

Validation, exception handling, and logging are handled automatically and uniformly across all endpoints.

## Installation

```bash
composer require bulatronic/api-kit
```

The bundle is automatically registered via Symfony Flex.

## Quick Start

### 1. Create a DTO

```php
final readonly class CreatePostDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $title,

        #[Assert\NotBlank]
        public string $content,
    ) {}
}
```

### 2. Create a Controller

Two options — pick one:

**Option A: extend `AbstractApiController`** (simplest, when you don't extend another class):

```php
use ApiKit\Controller\AbstractApiController;

#[Route('/api/posts')]
final class PostController extends AbstractApiController
{
    public function __construct(
        private readonly PostService $postService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->respondSuccess($this->postService->findAll());
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
    {
        return $this->respondCreated($this->postService->create($dto));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->postService->delete($id);
        return $this->respondNoContent();
    }
}
```

**Option B: use `ApiControllerTrait`** (when you already extend another class):

```php
use ApiKit\Controller\ApiControllerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/posts')]
final class PostController extends AbstractController
{
    use ApiControllerTrait;

    // ... same methods
}
```

### 3. Throw Errors from Services — No try/catch Needed

```php
use ApiKit\Exception\ApiException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PostService
{
    public function findOrFail(int $id): Post
    {
        $post = $this->repository->find($id);

        if (null === $post) {
            throw new NotFoundHttpException('Post not found');
        }

        return $post;
    }

    public function create(CreatePostDto $dto): Post
    {
        if ($this->repository->existsByTitle($dto->title)) {
            throw new ApiException(409, 'Post with this title already exists', [
                'field' => 'title',
                'value' => $dto->title,
            ]);
        }

        // ...
    }
}
```

`ExceptionListener` catches everything automatically. Your controller stays clean:

```php
public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
{
    return $this->respondCreated($this->postService->create($dto));
}
```

### 4. Standardized Response Format

**Success (200):**
```json
{
    "success": true,
    "data": [{"id": 1, "title": "Post 1"}],
    "meta": {"timestamp": "2026-02-23T12:00:00+00:00"}
}
```

**Validation error (422) — from `#[MapRequestPayload]`:**
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation error",
        "details": {
            "violations": [
                {"field": "title", "message": "This value should not be blank."}
            ]
        }
    }
}
```

**`ApiException` error (409):**
```json
{
    "success": false,
    "error": {
        "code": "CONFLICT",
        "message": "Post with this title already exists",
        "details": {
            "field": "title",
            "value": "My Post"
        }
    }
}
```

## Reference

### `AbstractApiController` / `ApiControllerTrait` Methods

```php
// Success responses
$this->respondSuccess($data, $status = 200, $meta = []);
$this->respondCreated($data, $meta = []);
$this->respondNoContent();

// Error responses
$this->respondError($message, $status = 400, $code = 'ERROR', $details = []);
$this->respondNotFound($message = 'Resource not found');
$this->respondForbidden($message = 'Access forbidden');
$this->respondUnauthorized($message = 'Unauthorized');
```

### `ResponseFactory` (inject as dependency)

```php
public function __construct(
    private readonly ResponseFactory $responseFactory,
) {}

$this->responseFactory->success($data, $statusCode = 200, $meta = []);
$this->responseFactory->created($data, $meta = []);
$this->responseFactory->noContent();
$this->responseFactory->error($message, $code = 'ERROR', $statusCode = 400, $details = []);
```

### `ApiException`

```php
use ApiKit\Exception\ApiException;

// Without details
throw new ApiException(409, 'Email already taken');

// With structured details
throw new ApiException(423, 'Account locked', [
    'locked_until'  => $until->format(\DateTimeInterface::ATOM),
    'reason'        => 'too_many_attempts',
]);
```

### `EntityExists` Validator

**Requires Doctrine ORM:**
```bash
composer require doctrine/orm doctrine/doctrine-bundle
```

```php
use ApiKit\Validator\Constraint\EntityExists;

final readonly class CreateCommentDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $content,

        #[Assert\Uuid]
        #[EntityExists(User::class)]
        public string $authorId,

        // Search by field other than id
        #[EntityExists(entityClass: Category::class, field: 'slug')]
        public ?string $categorySlug = null,
    ) {}
}
```

### Exception Handling

`ExceptionListener` handles automatically (no `try/catch` in controllers needed):

| Exception | HTTP Status | Notes |
|-----------|------------|-------|
| `ValidationFailedException` | 422 | From `#[MapRequestPayload]` or manual |
| `HttpException(*, prev: ValidationFailed)` | Same as exception | Violations extracted |
| `ApiException` | Configured code | `getDetails()` included in response |
| Any `HttpExceptionInterface` | Status from exception | Standard Symfony exceptions |
| Any other `\Throwable` | 500 | Logged; trace shown in debug mode |

## Configuration

Create `config/packages/api_kit.yaml` (optional — sensible defaults work out of the box):

```yaml
api_kit:
    response:
        include_timestamp: true           # Include timestamp in responses
        pretty_print: '%kernel.debug%'    # Pretty-print JSON in debug mode

    exception_handling:
        log_errors: true                  # Log server errors (5xx only)
        show_trace: '%kernel.debug%'      # Include stack trace in 500 responses
```

## Testing

```bash
# Run tests
composer test

# PHPStan static analysis
composer phpstan

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## Requirements

- PHP 8.5+
- Symfony 7.4+

**Optional:**
- Doctrine ORM — required for `EntityExists` validator

## Documentation

- [Usage Examples](docs/examples.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Development Guide](docs/DEVELOPMENT.md)
- [Contributing](CONTRIBUTING.md)

## License

MIT — see [LICENSE](LICENSE).

## Author

**Bulat Timerbaev** — bulat.coder@gmail.com
