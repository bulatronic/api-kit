# Usage Examples

Real-world examples based on a working Users + Posts blog API.

> **Note.** This is an intentionally simple blog demo. Architectural patterns such as
> CQRS, domain events, hexagonal layers, or dedicated write/read models are deliberately
> omitted to keep the focus on ApiKit itself — not on project structure decisions.

## Table of Contents

- [Users CRUD](#users-crud)
- [Posts CRUD with EntityExists](#posts-crud-with-entityexists)
- [ApiException for Business Rules](#apiexception-for-business-rules)
- [Pagination with Meta](#pagination-with-meta)
- [Injecting ResponseFactory Directly](#injecting-responsefactory-directly)
- [Testing Your Endpoints](#testing-your-endpoints)
- [Extending ResponseFactory](#extending-responsefactory) (add methods or replace format)
- [File Uploads](#file-uploads)
- [OpenAPI / Swagger Integration](#openapi--swagger-integration)

---

## Users CRUD

A complete Users resource: Entity → Repository → DTOs → Service → Controller.

### Entity

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 100)]
    private string $name = '';

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
}
```

### Repository

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('1')
            ->where('u.email = :email')
            ->setParameter('email', $email);

        if ($excludeId !== null) {
            $qb->andWhere('u.id != :id')->setParameter('id', $excludeId);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
```

### DTOs

```php
<?php

declare(strict_types=1);

namespace App\Dto\User;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Create user payload', required: ['email', 'name'])]
final readonly class CreateUserDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        public string $name,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Dto\User;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Update user payload (all fields optional)')]
final readonly class UpdateUserDto
{
    public function __construct(
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public ?string $email = null,

        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'User response')]
final readonly class UserResponseDto
{
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
        );
    }
}
```

### Service

```php
<?php

declare(strict_types=1);

namespace App\Service;

use ApiKit\Exception\ApiException;
use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /** @return list<User> */
    public function findAll(): array
    {
        return $this->userRepository->findBy([], ['id' => 'ASC']);
    }

    public function findOrFail(int $id): User
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException('User not found');
        }
        return $user;
    }

    public function create(CreateUserDto $dto): User
    {
        if ($this->userRepository->existsByEmail($dto->email)) {
            throw new ApiException(409, 'User with this email already exists', [
                'field' => 'email',
                'value' => $dto->email,
            ]);
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setName($dto->name);
        $this->userRepository->getEntityManager()->persist($user);
        $this->userRepository->getEntityManager()->flush();

        return $user;
    }

    public function update(int $id, UpdateUserDto $dto): User
    {
        $user = $this->findOrFail($id);

        if ($dto->email !== null) {
            if ($this->userRepository->existsByEmail($dto->email, $id)) {
                throw new ApiException(409, 'User with this email already exists', [
                    'field' => 'email',
                    'value' => $dto->email,
                ]);
            }
            $user->setEmail($dto->email);
        }
        if ($dto->name !== null) {
            $user->setName($dto->name);
        }

        $this->userRepository->getEntityManager()->flush();

        return $user;
    }

    public function delete(int $id): void
    {
        $user = $this->findOrFail($id);
        $this->userRepository->getEntityManager()->remove($user);
        $this->userRepository->getEntityManager()->flush();
    }
}
```

### Controller

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use ApiKit\Controller\AbstractApiController;
use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Dto\User\UserResponseDto;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_')]
final class UserController extends AbstractApiController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $users = $this->userService->findAll();

        return $this->respondSuccess(array_map(UserResponseDto::fromEntity(...), $users));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        return $this->respondSuccess(UserResponseDto::fromEntity($this->userService->findOrFail($id)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateUserDto $dto): JsonResponse
    {
        return $this->respondCreated(UserResponseDto::fromEntity($this->userService->create($dto)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] UpdateUserDto $dto): JsonResponse
    {
        return $this->respondSuccess(UserResponseDto::fromEntity($this->userService->update($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->respondNoContent();
    }
}
```

### HTTP Examples

**POST /api/users** — create a user

```http
POST /api/users
Content-Type: application/json

{
    "email": "author@example.com",
    "name": "Mr. Author"
}
```

```json
HTTP/1.1 201 Created

{
    "success": true,
    "data": {
        "id": 1,
        "email": "author@example.com",
        "name": "Mr. Author"
    },
    "meta": {
        "timestamp": "2026-02-26T11:21:42+00:00"
    }
}
```

**GET /api/users/1** — found

```json
HTTP/1.1 200 OK

{
    "success": true,
    "data": {
        "id": 1,
        "email": "author@example.com",
        "name": "Mr. Author"
    },
    "meta": {
        "timestamp": "2026-02-26T11:24:51+00:00"
    }
}
```

**GET /api/users/99** — not found (`NotFoundHttpException` → `ExceptionListener`)

```json
HTTP/1.1 404 Not Found

{
    "success": false,
    "error": {
        "code": "NOT_FOUND",
        "message": "User not found"
    }
}
```

---

## Posts CRUD with EntityExists

Posts are related to Users. The `authorId` field is validated with `EntityExists` directly in the DTO — the service receives a guaranteed-valid object.

### Entity

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $content = '';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt {
        get { return $this->createdAt; }
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }
}
```

### Repository

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Post> */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }
}
```

### DTOs

`#[EntityExists]` runs a DB query inside the Symfony validator — the field is checked before the controller action executes:

```php
<?php

declare(strict_types=1);

namespace App\Dto\Post;

use ApiKit\Validator\Constraint\EntityExists;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Create post payload', required: ['title', 'content', 'authorId'])]
final readonly class CreatePostDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 255)]
        public string $title,

        #[Assert\NotBlank]
        public string $content,

        #[Assert\NotNull]
        #[EntityExists(User::class)]
        public int $authorId,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Dto\Post;

use ApiKit\Validator\Constraint\EntityExists;
use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Update post payload (all fields optional)')]
final readonly class UpdatePostDto
{
    public function __construct(
        #[Assert\Length(min: 1, max: 255)]
        public ?string $title = null,

        public ?string $content = null,

        #[EntityExists(User::class)]
        public ?int $authorId = null,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Dto\Post;

use App\Entity\Post;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Post in API response')]
final readonly class PostResponseDto
{
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public int $authorId,
        public string $authorName,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(Post $post): self
    {
        $author = $post->getAuthor();

        return new self(
            id: (int) $post->getId(),
            title: $post->getTitle(),
            content: $post->getContent(),
            authorId: (int) $author?->getId(),
            authorName: $author !== null ? $author->getName() : '',
            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
        );
    }
}
```

### Service

Because `EntityExists` already guaranteed `authorId` exists, the service can load the author without a defensive check:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Post\CreatePostDto;
use App\Dto\Post\UpdatePostDto;
use App\Entity\Post;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PostService
{
    public function __construct(
        private PostRepository $postRepository,
        private UserRepository $userRepository,
    ) {
    }

    /** @return list<Post> */
    public function findAll(): array
    {
        return $this->postRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function findOrFail(int $id): Post
    {
        $post = $this->postRepository->find($id);
        if ($post === null) {
            throw new NotFoundHttpException('Post not found');
        }

        return $post;
    }

    public function create(CreatePostDto $dto): Post
    {
        $author = $this->userRepository->find($dto->authorId);
        if ($author === null) {
            throw new NotFoundHttpException('Author not found');
        }

        $post = new Post();
        $post->setTitle($dto->title);
        $post->setContent($dto->content);
        $post->setAuthor($author);
        $this->postRepository->getEntityManager()->persist($post);
        $this->postRepository->getEntityManager()->flush();

        return $post;
    }

    public function update(int $id, UpdatePostDto $dto): Post
    {
        $post = $this->findOrFail($id);

        if ($dto->title !== null) {
            $post->setTitle($dto->title);
        }
        if ($dto->content !== null) {
            $post->setContent($dto->content);
        }
        if ($dto->authorId !== null) {
            $author = $this->userRepository->find($dto->authorId);
            if ($author === null) {
                throw new NotFoundHttpException('Author not found');
            }
            $post->setAuthor($author);
        }

        $this->postRepository->getEntityManager()->flush();

        return $post;
    }

    public function delete(int $id): void
    {
        $post = $this->findOrFail($id);
        $this->postRepository->getEntityManager()->remove($post);
        $this->postRepository->getEntityManager()->flush();
    }
}
```

### Controller

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use ApiKit\Controller\AbstractApiController;
use App\Dto\Post\CreatePostDto;
use App\Dto\Post\PostResponseDto;
use App\Dto\Post\UpdatePostDto;
use App\Service\PostService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/posts', name: 'api_posts_')]
final class PostController extends AbstractApiController
{
    public function __construct(
        private readonly PostService $postService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $posts = $this->postService->findAll();

        return $this->respondSuccess(array_map(PostResponseDto::fromEntity(...), $posts));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        return $this->respondSuccess(PostResponseDto::fromEntity($this->postService->findOrFail($id)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
    {
        return $this->respondCreated(PostResponseDto::fromEntity($this->postService->create($dto)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] UpdatePostDto $dto): JsonResponse
    {
        return $this->respondSuccess(PostResponseDto::fromEntity($this->postService->update($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->postService->delete($id);

        return $this->respondNoContent();
    }
}
```

### HTTP Examples

**POST /api/posts** — create with valid author

```http
POST /api/posts
Content-Type: application/json

{
    "title": "Getting Started with Web Development",
    "content": "Web development is an exciting field...",
    "authorId": 1
}
```

```json
HTTP/1.1 201 Created

{
    "success": true,
    "data": {
        "id": 1,
        "title": "Getting Started with Web Development",
        "content": "Web development is an exciting field...",
        "authorId": 1,
        "authorName": "Mr. Author",
        "createdAt": "2026-02-26T11:23:14+00:00"
    },
    "meta": {
        "timestamp": "2026-02-26T11:23:14+00:00"
    }
}
```

**POST /api/posts** — non-existent `authorId` (EntityExists fails → 422)

```http
POST /api/posts
Content-Type: application/json

{
    "title": "10 Tips for Better Time Management",
    "content": "Do you often feel like there aren't enough hours...",
    "authorId": 3
}
```

```json
HTTP/1.1 422 Unprocessable Entity

{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation error",
        "details": {
            "violations": [
                {
                    "field": "authorId",
                    "message": "Entity \"User\" with id = \"3\" not found."
                }
            ]
        }
    }
}
```

**GET /api/posts** — list (sorted by `createdAt DESC`)

```json
HTTP/1.1 200 OK

{
    "success": true,
    "data": [
        {
            "id": 2,
            "title": "10 Tips for Better Time Management",
            "content": "Do you often feel like there aren't enough hours...",
            "authorId": 1,
            "authorName": "Mr. Author",
            "createdAt": "2026-02-26T11:28:05+00:00"
        },
        {
            "id": 1,
            "title": "Getting Started with Web Development",
            "content": "Web development is an exciting field...",
            "authorId": 1,
            "authorName": "Mr. Author",
            "createdAt": "2026-02-26T11:23:14+00:00"
        }
    ],
    "meta": {
        "timestamp": "2026-02-26T11:28:08+00:00"
    }
}
```

---

## ApiException for Business Rules

Use `ApiException` when you need to return a structured error with custom details — for example, a unique-constraint violation with the conflicting field name:

```php
use ApiKit\Exception\ApiException;

public function create(CreateUserDto $dto): User
{
    if ($this->userRepository->existsByEmail($dto->email)) {
        throw new ApiException(409, 'User with this email already exists', [
            'field' => 'email',
            'value' => $dto->email,
        ]);
    }
    // ...
}
```

```json
HTTP/1.1 409 Conflict

{
    "success": false,
    "error": {
        "code": "CONFLICT",
        "message": "User with this email already exists",
        "details": {
            "field": "email",
            "value": "author@example.com"
        }
    }
}
```

The controller needs no `try/catch` — `ExceptionListener` handles it automatically:

```php
#[Route('', name: 'create', methods: ['POST'])]
public function create(#[MapRequestPayload] CreateUserDto $dto): JsonResponse
{
    return $this->respondCreated(UserResponseDto::fromEntity($this->userService->create($dto)));
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
        "timestamp": "2026-02-26T12:00:00+00:00",
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

If you don't extend a controller (e.g. in an event listener):

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

final class UserControllerTest extends WebTestCase
{
    public function testCreateReturns201(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'test@example.com', 'name' => 'Test User']),
        );

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('test@example.com', $data['data']['email']);
    }

    public function testGetNotFoundReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/users/999999');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('NOT_FOUND', $data['error']['code']);
    }

    public function testCreateWithInvalidEmailReturns422(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'not-an-email', 'name' => 'Test']),
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertNotEmpty($data['error']['details']['violations']);
    }

    public function testCreatePostWithNonExistentAuthorReturns422(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/posts',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'My Post', 'content' => 'Content', 'authorId' => 9999]),
        );

        $this->assertResponseStatusCodeSame(422);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $violations = $data['error']['details']['violations'];
        $this->assertSame('authorId', $violations[0]['field']);
    }

    public function testDuplicateEmailReturns409(): void
    {
        $client = static::createClient();
        $payload = json_encode(['email' => 'dup@example.com', 'name' => 'Dup']);

        $client->request('POST', '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/users',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $this->assertResponseStatusCodeSame(409);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('CONFLICT', $data['error']['code']);
    }

    public function testDeleteReturns204(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/users/1');

        $this->assertResponseStatusCodeSame(204);
    }
}
```

---

## Extending ResponseFactory

There are two ways to customize responses depending on what you need.

### Add methods — extend `ResponseFactory`

Extend `ResponseFactory` to add project-specific helpers while keeping the default format:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use ApiKit\Response\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class AppResponseFactory extends ResponseFactory
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

```yaml
# config/services.yaml
ApiKit\Response\ResponseFactory:
    class: App\Api\AppResponseFactory
```

### Replace the format — implement `ResponseFactoryInterface`

If your team uses a different response envelope, implement `ResponseFactoryInterface` from scratch.
`ExceptionListener` will use your factory for all error responses too, so the format stays consistent:

```php
<?php

declare(strict_types=1);

namespace App\Api;

use ApiKit\Response\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final readonly class MyResponseFactory implements ResponseFactoryInterface
{
    public function success(mixed $data = null, int $statusCode = 200, array $meta = []): JsonResponse
    {
        $body = ['ok' => true, 'result' => $data];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $statusCode);
    }

    public function error(
        string $message,
        string $code = 'ERROR',
        int $statusCode = 400,
        array $details = [],
    ): JsonResponse {
        $body = ['ok' => false, 'error' => $code, 'message' => $message];
        if ($details) {
            $body['details'] = $details;
        }

        return new JsonResponse($body, $statusCode);
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

---

## File Uploads

`#[MapRequestPayload]` deserializes JSON only — for file uploads use Symfony's `#[MapUploadedFile]` (Symfony 7.1+).
`ExceptionListener` automatically converts validation errors (wrong MIME type, size exceeded, bad dimensions) into standardized 422 responses — no extra code needed.

> `Assert\Video` requires Symfony 7.4+ and **ffprobe** installed on the server (`apt install ffmpeg`).

### FileUploader Service

A minimal service that moves the uploaded file to the public directory and returns the relative path:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use Random\RandomException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class FileUploader
{
    public function __construct(
        private string $publicDir,
    ) {
    }

    /** @throws RandomException */
    public function upload(UploadedFile $file, string $subDir): string
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
        $file->move($this->publicDir . '/uploads/' . $subDir, $filename);

        return 'uploads/' . $subDir . '/' . $filename;
    }
}
```

Wire it in `services.yaml`:

```yaml
App\Service\FileUploader:
    arguments:
        $publicDir: '%kernel.project_dir%/public'
```

### Image Upload (Avatar)

Validates the file directly in the controller argument via `#[MapUploadedFile]`. If validation fails, `ExceptionListener` returns a 422 with violations — the service never receives an invalid file.

**Entity** — add `avatarPath` field to `User`:

```php
#[ORM\Column(length: 255, nullable: true)]
private ?string $avatarPath = null;

public function getAvatarPath(): ?string { return $this->avatarPath; }
public function setAvatarPath(?string $avatarPath): static { $this->avatarPath = $avatarPath; return $this; }
```

**Service** — `UserService`:

```php
public function updateAvatar(int $id, UploadedFile $avatar): User
{
    $user = $this->findOrFail($id);
    $path = $this->fileUploader->upload($avatar, 'avatars');
    $user->setAvatarPath($path);
    $this->userRepository->getEntityManager()->flush();

    return $user;
}
```

**Controller** — endpoint in `UserController`:

```php
#[Route('/{id}/avatar', name: 'upload_avatar', requirements: ['id' => '\d+'], methods: ['POST'])]
public function uploadAvatar(
    int $id,
    #[MapUploadedFile([
        new Assert\NotNull(message: 'Avatar file is required'),
        new Assert\Image(
            maxSize: '5M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Only JPEG, PNG and WebP images are allowed',
            maxWidth: 2048,
            maxHeight: 2048,
        ),
    ])]
    UploadedFile $avatar,
): JsonResponse {
    return $this->respondSuccess(
        UserResponseDto::fromEntity($this->userService->updateAvatar($id, $avatar))
    );
}
```

**HTTP Example — success:**

```http
POST /api/users/1/avatar
Content-Type: multipart/form-data

avatar=<file: photo.jpg>
```

```json
HTTP/1.1 200 OK

{
    "success": true,
    "data": {
        "id": 1,
        "email": "author@example.com",
        "name": "Mr. Author",
        "avatarPath": "uploads/avatars/3f8a1c2e9b0d4e7f.jpg"
    },
    "meta": {
        "timestamp": "2026-02-28T10:00:00+00:00"
    }
}
```

**HTTP Example — validation error (wrong MIME type → 422):**

```json
HTTP/1.1 422 Unprocessable Entity

{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation error",
        "details": {
            "violations": [
                {
                    "field": "avatar",
                    "message": "Only JPEG, PNG and WebP images are allowed"
                }
            ]
        }
    }
}
```

### Video Upload

Requires Symfony 7.4+ and `ffprobe` on the server. Create a dedicated `MediaController`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use ApiKit\Controller\AbstractApiController;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/media', name: 'api_media_')]
final class MediaController extends AbstractApiController
{
    public function __construct(
        private readonly FileUploader $fileUploader,
    ) {
    }

    #[Route('/video', name: 'upload_video', methods: ['POST'])]
    public function uploadVideo(
        #[MapUploadedFile([
            new Assert\NotNull(message: 'Video file is required'),
            new Assert\Video(
                maxSize: '100M',
                mimeTypes: ['video/mp4', 'video/webm'],
                maxWidth: 1920,
                maxHeight: 1080,
                mimeTypesMessage: 'Only MP4 and WebM videos are allowed',
            ),
        ])]
        UploadedFile $video,
    ): JsonResponse {
        $path = $this->fileUploader->upload($video, 'videos');

        return $this->respondSuccess([
            'path'         => $path,
            'originalName' => $video->getClientOriginalName(),
            'size'         => $video->getSize(),
            'mimeType'     => $video->getMimeType(),
        ]);
    }
}
```

**HTTP Example — success:**

```http
POST /api/media/video
Content-Type: multipart/form-data

video=<file: clip.mp4>
```

```json
HTTP/1.1 200 OK

{
    "success": true,
    "data": {
        "path": "uploads/videos/9a4c2f1b8e3d5a0c.mp4",
        "originalName": "clip.mp4",
        "size": 10485760,
        "mimeType": "video/mp4"
    },
    "meta": {
        "timestamp": "2026-02-28T10:05:00+00:00"
    }
}
```

### Mixed Multipart (JSON Fields + File)

Combine `#[MapRequestPayload]` and `#[MapUploadedFile]` in the same action:

```php
#[Route('', name: 'create', methods: ['POST'])]
public function create(
    #[MapRequestPayload] CreatePostDto $dto,
    #[MapUploadedFile([
        new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
    ])]
    ?UploadedFile $thumbnail = null,
): JsonResponse {
    return $this->respondCreated(
        PostResponseDto::fromEntity($this->postService->create($dto, $thumbnail))
    );
}
```

The client sends JSON fields + file in a single `multipart/form-data` request. Both are validated independently; any failure returns a 422.

---

## OpenAPI / Swagger Integration

ApiKit does not include OpenAPI support — use [`nelmio/api-doc-bundle`](https://github.com/nelmio/NelmioApiDocBundle) for that.
The two integrate naturally: ApiKit handles runtime responses, OpenAPI describes them in documentation.

```bash
composer require nelmio/api-doc-bundle
```

Annotate DTOs with `#[OA\Schema]` and controllers with `#[OA\Get]` / `#[OA\Post]` etc.
Use `new Model(type: SomeDto::class)` to reference DTO schemas — nelmio reads property types and constraints automatically.

### DTO with `#[OA\Schema]`

```php
<?php

declare(strict_types=1);

namespace App\Dto\User;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(description: 'Create user payload', required: ['email', 'name'])]
final readonly class CreateUserDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        #[OA\Property(example: 'user@example.com')]
        public string $email,

        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 100)]
        #[OA\Property(example: 'Mr. Author')]
        public string $name,
    ) {
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Entity\User;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'User response')]
final readonly class UserResponseDto
{
    public function __construct(
        #[OA\Property(example: 1)]
        public int $id,
        #[OA\Property(example: 'user@example.com')]
        public string $email,
        #[OA\Property(example: 'Mr. Author')]
        public string $name,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
        );
    }
}
```

### Controller with `#[OA\*]` attributes

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use ApiKit\Controller\AbstractApiController;
use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Dto\User\UserResponseDto;
use App\Service\UserService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_')]
#[OA\Tag(name: 'Users', description: 'Users CRUD')]
final class UserController extends AbstractApiController
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        operationId: 'listUsers',
        summary: 'List all users',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of users',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: UserResponseDto::class))
                )
            ),
        ]
    )]
    public function list(): JsonResponse
    {
        $users = $this->userService->findAll();

        return $this->respondSuccess(array_map(UserResponseDto::fromEntity(...), $users));
    }

    #[Route('/{id}', name: 'get', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        operationId: 'getUser',
        summary: 'Get one user by id',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User found',
                content: new OA\JsonContent(ref: new Model(type: UserResponseDto::class))
            ),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function get(int $id): JsonResponse
    {
        return $this->respondSuccess(UserResponseDto::fromEntity($this->userService->findOrFail($id)));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        operationId: 'createUser',
        summary: 'Create a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreateUserDto::class))
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created',
                content: new OA\JsonContent(ref: new Model(type: UserResponseDto::class))
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 409, description: 'Email already taken'),
        ]
    )]
    public function create(#[MapRequestPayload] CreateUserDto $dto): JsonResponse
    {
        return $this->respondCreated(UserResponseDto::fromEntity($this->userService->create($dto)));
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}',
        operationId: 'updateUser',
        summary: 'Update an existing user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateUserDto::class))
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated',
                content: new OA\JsonContent(ref: new Model(type: UserResponseDto::class))
            ),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 409, description: 'Email already taken'),
        ]
    )]
    public function update(int $id, #[MapRequestPayload] UpdateUserDto $dto): JsonResponse
    {
        return $this->respondSuccess(UserResponseDto::fromEntity($this->userService->update($id, $dto)));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/users/{id}',
        operationId: 'deleteUser',
        summary: 'Delete a user',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->respondNoContent();
    }
}
```

`#[OA\Tag]` on the class groups all actions under one section in Swagger UI.
`new Model(type: SomeDto::class)` tells nelmio to generate a schema from the DTO — no manual schema duplication needed.
