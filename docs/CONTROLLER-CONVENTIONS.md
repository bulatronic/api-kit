# Controller Conventions

Concrete, codified rules for building controllers on top of ApiKit — written for both humans
and AI coding agents. Not about *how ApiKit works* (that's [README.md](../README.md) /
[ARCHITECTURE.md](ARCHITECTURE.md)) — about *how to write code that uses it correctly*.

If your project uses an AI coding agent (Cursor, Claude Code, Copilot, Codex, ...), copy the
sections below into whatever instruction file that agent reads for your project — `AGENTS.md`,
`CLAUDE.md`, `.github/copilot-instructions.md`, or your tool's own rules file. This file is
plain Markdown on purpose: no tool-specific front-matter, so it works no matter what your team
uses.

## Always

**Return through `respond*()` / `ResponseFactory`, never build the envelope by hand.**

```php
// wrong — ApiKit can no longer guarantee the envelope shape
return new JsonResponse(['success' => true, 'data' => $post]);

// right
return $this->respondSuccess($post);
return $this->respondCreated($post);
return $this->respondNoContent();
```

**Let exceptions bubble up to `ExceptionListener`, don't `try/catch` in controllers.**

```php
// wrong
public function create(...): JsonResponse
{
    try {
        return $this->respondCreated($this->service->create($dto));
    } catch (ConflictException $e) {
        return $this->respondError($e->getMessage(), 409);
    }
}

// right — throw ApiException from the service, the controller stays thin
public function create(...): JsonResponse
{
    return $this->respondCreated($this->service->create($dto));
}
```

**Keep controllers thin.** A controller method maps a request to a service call and a
`respond*()` call — no validation logic, no conditionals beyond routing, no direct
repository/Doctrine access.

**Validate input with DTOs + native Symfony attributes**, not manual `if` checks:

```php
public function create(#[MapRequestPayload] CreatePostDto $dto): JsonResponse
```

**Return DTOs, never Entities, from a controller.** If you find yourself passing a Doctrine
entity straight into `respondSuccess()`, stop and write a response DTO first — Entities carry
lazy-loading proxies, ORM metadata, and fields that were never meant to be public API surface.

```php
// wrong — serializes Doctrine proxies, fields, and other ORM internals
return $this->respondSuccess($post); // $post is App\Entity\Post

// right
return $this->respondSuccess(PostResponseDto::fromEntity($post));
```

## Ask first

- Introducing a new top-level error `code` that isn't already used elsewhere in the project —
  check for an existing equivalent (`NOT_FOUND`, `VALIDATION_ERROR`, `CONFLICT`, ...) before
  inventing a new one.
- Changing the shape of an existing endpoint's `data`/`error.details` — this is a breaking
  change for any client (and for the OpenAPI schema, if documented — see
  [OPENAPI.md](OPENAPI.md)).
- Adding a new response shape ApiKit doesn't have a helper for yet (e.g. cursor-paginated
  lists) — decide the convention once, consistently, rather than per-endpoint.

## Never

- **Never** build the `{success, ...}` / `{success: false, error: ...}` envelope manually
  outside of `ResponseFactory`/`ExceptionListener` — that duplicates logic that already exists
  and *will* drift from it.
- **Never** use `Request` inside a service/use-case class. Extract everything the service needs
  into a DTO in the controller (or via `#[MapRequestPayload]`/`#[MapQueryString]`) — services
  should be framework-agnostic and testable without an HTTP request.
- **Never** put request/response DTOs in the same namespace/layer as your domain entities.
  Keep them in a dedicated layer (e.g. `Application/DTO`, or wherever your architecture puts
  transport-facing objects — see the compatibility table in
  [ARCHITECTURE.md](ARCHITECTURE.md)) so the domain has zero knowledge of HTTP or serialization.
- **Never** write a custom exception-to-response mapping in a controller. Throw `ApiException`
  (with a status code + optional `details`) or a standard `HttpExceptionInterface` — the
  `ExceptionListener` already handles both.

## OpenAPI documentation, if you use it

If the project has `nelmio/api-doc-bundle` + `zircote/swagger-php` installed, document
responses with `ApiKit\OpenApi\Attribute\*` (`ApiSuccessResponse`, `ApiCreatedResponse`,
`ApiNoContentResponse`, `ApiErrorResponse`) — never hand-write `new OA\Response(...)` for a new
endpoint. Full reference: [OPENAPI.md](OPENAPI.md).

If those packages are **not** installed, don't add them just to document one endpoint — that's
a project-level decision, not something to make unilaterally while implementing a feature. Ask
first; in the meantime document by hand, but still wrap success in `{success, data, meta}` and
link errors to a schema that matches `ErrorEnvelope`/`ValidationErrorEnvelope`, so the manual
docs don't silently drift from what ApiKit actually returns at runtime.
