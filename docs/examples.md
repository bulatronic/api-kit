# Usage Examples

Practical examples for common scenarios.

## Table of Contents

- [Basic CRUD Controller](#basic-crud-controller)
- [Service Layer with ApiException](#service-layer-with-apiexception)
- [DTO with EntityExists Validation](#dto-with-entityexists-validation)
- [Pagination with Meta](#pagination-with-meta)
- [Injecting ResponseFactory Directly](#injecting-responsefactory-directly)
- [Testing Your Endpoints](#testing-your-endpoints)
- [Extending ResponseFactory](#extending-responsefactory)

---

## Basic CRUD Controller

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use ApiKit\Controller\AbstractApiController;
use App\DTO\CreatePostDto;
use App\DTO\UpdatePostDto;
use App\Service\PostService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        // PostService throws NotFoundHttpException if not found — no try/catch needed
        $post = $this->postService->findOrFail($id);

        return $this->respondSuccess($post);
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
    {
        return $this->respondCreated($this->postService->create($dto));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdatePostDto $dto,
    ): JsonResponse {
        return $this->respondSuccess($this->postService->update($id, $dto));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->postService->delete($id);

        return $this->respondNoContent();
    }
}
```

---

## Service Layer with ApiException

Throw exceptions from services — `ExceptionListener` converts them to JSON automatically:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use ApiKit\Exception\ApiException;
use App\DTO\CreatePostDto;
use App\Entity\Post;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PostService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PostRepository $repository,
    ) {}

    public function findOrFail(int $id): Post
    {
        $post = $this->repository->find($id);

        if (null === $post) {
            throw new NotFoundHttpException('Post not found');  // → 404
        }

        return $post;
    }

    public function create(CreatePostDto $dto): Post
    {
        if ($this->repository->existsBySlug($dto->slug)) {
            // ApiException carries structured details into the response
            throw new ApiException(409, 'Slug already taken', [
                'field' => 'slug',
                'value' => $dto->slug,
            ]);
        }

        $post = new Post($dto->title, $dto->content);

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    public function delete(int $id): void
    {
        $post = $this->findOrFail($id);

        if ($post->isPublished()) {
            throw new ApiException(403, 'Cannot delete published post', [
                'reason' => 'published',
                'published_at' => $post->getPublishedAt()->format(\DateTimeInterface::ATOM),
            ]);
        }

        $this->em->remove($post);
        $this->em->flush();
    }
}
```

The controller stays minimal:

```php
#[Route('/{id}', methods: ['DELETE'])]
public function delete(int $id): JsonResponse
{
    $this->postService->delete($id);  // throws → caught by ExceptionListener
    return $this->respondNoContent();
}
```

---

## DTO with EntityExists Validation

```php
<?php

declare(strict_types=1);

namespace App\DTO;

use ApiKit\Validator\Constraint\EntityExists;
use App\Entity\Category;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePostDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 255)]
        public string $title,

        #[Assert\NotBlank]
        public string $content,

        // Check that User with this UUID exists in DB
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[EntityExists(User::class)]
        public string $authorId,

        // Search by field other than id (e.g. slug)
        #[EntityExists(entityClass: Category::class, field: 'slug')]
        public ?string $categorySlug = null,

        #[Assert\Count(max: 10)]
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
        public array $tags = [],
    ) {}
}
```

Validation error response:

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation error",
        "details": {
            "violations": [
                {
                    "field": "authorId",
                    "message": "Entity \"User\" with id = \"non-existent-uuid\" not found."
                }
            ]
        }
    }
}
```

---

## Pagination with Meta

```php
#[Route('', methods: ['GET'])]
public function list(#[MapQueryString] PostsQueryDto $query): JsonResponse
{
    $result = $this->postService->paginate($query->page, $query->perPage);

    return $this->respondSuccess(
        data: $result->items,
        meta: [
            'pagination' => [
                'total'       => $result->total,
                'page'        => $result->page,
                'per_page'    => $result->perPage,
                'total_pages' => $result->totalPages,
            ],
        ],
    );
}
```

Response:

```json
{
    "success": true,
    "data": [...],
    "meta": {
        "timestamp": "2026-02-23T12:00:00+00:00",
        "pagination": {
            "total": 100,
            "page": 1,
            "per_page": 20,
            "total_pages": 5
        }
    }
}
```

---

## Injecting ResponseFactory Directly

If you don't use a controller at all (e.g. in a custom event listener or middleware):

```php
use ApiKit\Response\ResponseFactory;

final readonly class SomeListener
{
    public function __construct(
        private ResponseFactory $responseFactory,
    ) {}

    public function onSomeEvent(ExceptionEvent $event): void
    {
        $response = $this->responseFactory->error(
            message: 'Rate limit exceeded',
            code: 'RATE_LIMIT',
            statusCode: 429,
            details: ['retry_after' => 60],
        );

        $event->setResponse($response);
    }
}
```

---

## Testing Your Endpoints

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PostControllerTest extends WebTestCase
{
    public function testListReturnsSuccess(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/posts');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testCreateWithInvalidDataReturns422(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/posts',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => '', 'content' => 'test']),
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertNotEmpty($data['error']['details']['violations']);
    }

    public function testNotFoundReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/posts/999999');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('NOT_FOUND', $data['error']['code']);
    }

    public function testConflictReturns409WithDetails(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/posts',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'Existing Post', 'content' => 'test']),
        );

        $this->assertResponseStatusCodeSame(409);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('CONFLICT', $data['error']['code']);
        $this->assertArrayHasKey('details', $data['error']);
    }

    public function testDeleteReturnsNoContent(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/posts/1');

        $this->assertResponseStatusCodeSame(204);
    }
}
```

---

## Extending ResponseFactory

Add custom response methods for your project:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use ApiKit\Response\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AppResponseFactory extends ResponseFactory
{
    public function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage,
    ): JsonResponse {
        return $this->success(
            data: $items,
            meta: [
                'pagination' => [
                    'total'       => $total,
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
        );
    }

    public function accepted(mixed $data, string $jobId): JsonResponse
    {
        return $this->success(
            data: $data,
            statusCode: 202,
            meta: ['job_id' => $jobId],
        );
    }
}
```
