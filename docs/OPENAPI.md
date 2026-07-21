# OpenAPI Documentation Attributes

Optional attributes that document ApiKit's response envelope (`{success, data, meta}` /
`{success, error}`) for `nelmio/api-doc-bundle` + `zircote/swagger-php`, without you having
to hand-write it on every controller method.

`nelmio/api-doc-bundle` and `zircote/swagger-php` are **not** required by ApiKit — they are
declared as `require-dev`/`suggest`. If your project doesn't document its API with them, this
whole feature is dead code sitting unused in `vendor/`, with zero runtime cost: nothing in
`ApiKitBundle`/`ApiKitExtension` references `ApiKit\OpenApi\*`, so PHP never autoloads those
classes unless your own controller code does.

```bash
composer require nelmio/api-doc-bundle
```

## Why this exists

`api-kit` wraps every response in an envelope (`ResponseFactory`, `ExceptionListener`). Nothing
in `nelmio/api-doc-bundle` or `zircote/swagger-php` knows about that envelope, so every
controller ends up hand-writing it — and hand-written things drift from the code that actually
runs:

- **The envelope gets forgotten on success responses.** It's easy to write
  `content: new OA\JsonContent(ref: new Model(type: SomeDto::class))` for a `201`, which
  documents the DTO *without* the `{success, data}` wrapper — while the controller actually
  calls `respondCreated()`, which **does** wrap it. The schema and the runtime response
  disagree, silently.
- **Error responses usually have no schema at all.** `401`/`403`/`404`/`409`/`422`/`429` are
  typically listed with just a `description` and no `content`, because writing the envelope by
  hand for every error status isn't worth the effort — so nobody does it.

Problem A is a real bug (schema lies about the response shape). Problem B is a real gap (schema
says nothing at all). These attributes make the correct, enveloped form the *laziest* one to
type — one attribute per response instead of an inline `OA\JsonContent(properties: [...])`
block.

## What this deliberately does **not** do

- **No processor, no listener, no autowiring magic.** Nothing inspects a plain `OA\Response`
  and wraps it automatically. Every attribute here is explicit — you add it, or you don't.
- **It does not replace anything.** The old, hand-written `new OA\Response(...)` style keeps
  working exactly as before. Migration is per-endpoint: one controller can use the new
  attributes, its neighbour can keep the old style, forever if needed.

## Before / after

### Success envelope (the actual bug)

```php
// wrong — the schema lies: it documents CreateTweetResponseDto directly, with no envelope,
// while respondCreated() actually wraps it in { success, data, meta }.
new OA\Response(
    response: 201,
    description: 'Tweet created',
    content: new OA\JsonContent(ref: new Model(type: CreateTweetResponseDto::class)),
),
```

```php
// right — the envelope can't physically be skipped, because the attribute builds it for you.
#[ApiCreatedResponse(CreateTweetResponseDto::class, description: 'Tweet created')]
```

### Error envelope (the actual gap)

```php
// wrong — 0% chance anyone hand-writes content for every 4xx/5xx.
new OA\Response(response: 404, description: 'Tweet not found'),
```

```php
// right — no longer than the "lazy" version, but linked to a full schema.
#[ApiErrorResponse(404, 'Tweet not found')]
```

### Full controller example

```php
use ApiKit\OpenApi\Attribute\ApiCreatedResponse;
use ApiKit\OpenApi\Attribute\ApiErrorResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/tweets',
    summary: 'Create a tweet',
    security: [['Bearer' => []]],
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateTweetRequestDto::class))),
    tags: ['Tweets'],
)]
#[ApiCreatedResponse(CreateTweetResponseDto::class, description: 'Tweet created')]
#[ApiErrorResponse(401, 'Not authenticated')]
#[ApiErrorResponse(422, 'Validation error (empty or too long text)', isValidation: true)]
#[ApiErrorResponse(429, 'Tweet creation rate limit exceeded')]
#[Route('/api/tweets', name: 'api_tweets_create', methods: ['POST'])]
public function __invoke(#[MapRequestPayload] CreateTweetRequestDto $request): JsonResponse
{
    return $this->respondCreated(['id' => $tweetId]);
}
```

`ApiCreatedResponse`/`ApiErrorResponse` here are **siblings** of `#[OA\Post]` on the same
method, not nested inside a `responses: [...]` array — `swagger-php` merges repeated
`Response`-shaped attributes attached to the same method into that operation automatically
(`IS_REPEATABLE`), which is exactly why every attribute below is declared
`#[\Attribute(TARGET_METHOD | IS_REPEATABLE)]`.

> If your response DTO doesn't exist yet (e.g. the controller currently returns an inline array
> like `['id' => $tweetId]`), add a small response DTO (`final readonly class
> CreateTweetResponseDto { public function __construct(public string $id) {} }`) —
> `dataType` needs a class to describe via `#[Model]`.

## Reference

### `ApiSuccessResponse`

```php
#[ApiSuccessResponse(
    dataType: TweetDto::class,  // FQCN described via Nelmio's #[Model]
    status: 200,                // default
    description: 'OK',          // default
    isArray: false,             // true => `data` is an array of `$dataType`, not a single ref
)]
```

```php
// single object — data: { $ref: TweetDto }
#[ApiSuccessResponse(TweetDto::class)]

// plain list — data: [ { $ref: TweetDto }, ... ]
#[ApiSuccessResponse(TweetDto::class, isArray: true)]
```

Produces:

```json
{
    "success": { "type": "boolean", "example": true },
    "data": "... single $ref or array of $ref, per isArray ...",
    "meta": { "type": "object", "nullable": true }
}
```

### `ApiCreatedResponse`

Same as `ApiSuccessResponse`, `status` defaults to `201` and `description` defaults to
`'Created'`.

```php
#[ApiCreatedResponse(CreateTweetResponseDto::class)]
#[ApiCreatedResponse(CreateTweetResponseDto::class, isArray: true)] // batch-create style endpoints
```

### `ApiNoContentResponse`

`204`, no `content` at all — matches `ResponseFactory::noContent()`.

```php
#[ApiNoContentResponse]
#[ApiNoContentResponse('Tweet deleted')]
```

### `ApiErrorResponse`

Links to the reusable `ErrorEnvelope` / `ValidationErrorEnvelope` schema instead of inlining
anything, so it stays exactly as short as the content-less version people currently write out
of habit.

```php
#[ApiErrorResponse(404, 'Tweet not found')]
#[ApiErrorResponse(422, 'Validation error', isValidation: true)] // -> ValidationErrorEnvelope
```

## Registering the reusable schemas

`ApiErrorResponse` links to `#/components/schemas/ErrorEnvelope` and
`.../ValidationErrorEnvelope` by `$ref`. Those schemas are defined once, in
`ApiKit\OpenApi\Schema\EnvelopeSchemas`, using plain `#[OA\Schema]` attributes. The only
remaining question is: how do you get `nelmio/api-doc-bundle` to actually register them in
`components.schemas`?

**This is the one manual step you cannot skip**, and — unlike everything else here — it
depends on your `nelmio/api-doc-bundle` version, because `nelmio/api-doc-bundle` does **not**
blanket-scan every PHP file for `#[OA\Schema]` the way raw `zircote/swagger-php` does; it only
picks up classes it is explicitly told about (there is no `models_to_scan` option in current
4.x/5.x releases — that name is from older, pre-attribute docs). Two ways that reliably work
today:

### Option A — paste the schema into YAML (most reliable, zero PHP-scanning surprises)

Generate the JSON once with `zircote/swagger-php`'s own CLI (works regardless of `nelmio`
version, because it bypasses `nelmio`'s scanning entirely):

```bash
vendor/bin/openapi --format yaml vendor/bulatronic/api-kit/src/OpenApi/Schema -o /tmp/envelope-schemas.yaml
```

... and paste the resulting `ErrorEnvelope`/`ValidationErrorEnvelope` definitions under
`documentation.components.schemas` in your own `config/packages/nelmio_api_doc.yaml`, next to
your other manually-registered pieces (e.g. `securitySchemes.Bearer`):

```yaml
nelmio_api_doc:
    documentation:
        components:
            schemas:
                ErrorEnvelope:
                    type: object
                    required: [ success, error ]
                    properties:
                        success: { type: boolean, example: false }
                        error:
                            type: object
                            required: [ code, message ]
                            properties:
                                code: { type: string, example: NOT_FOUND }
                                message: { type: string, example: Resource not found }
                                details: { type: object, nullable: true }
                ValidationErrorEnvelope:
                    allOf:
                        - $ref: '#/components/schemas/ErrorEnvelope'
                        - type: object
                          properties:
                              error:
                                  type: object
                                  properties:
                                      details:
                                          type: object
                                          properties:
                                              violations:
                                                  type: array
                                                  items:
                                                      type: object
                                                      properties:
                                                          field: { type: string, nullable: true }
                                                          message: { type: string }
```

### Option B — reference `EnvelopeSchemas` via `#[Model]`

`nelmio/api-doc-bundle` reads a class's own `#[OA\Schema]` attributes when that class is
referenced through `Nelmio\ApiDocBundle\Attribute\Model` (this is the same mechanism used to
override a model's schema type in Nelmio's own docs). Referencing it once anywhere is enough:

```php
#[OA\Get(path: '/api/_envelope-schemas', /* any low-traffic or internal-only route */)]
#[OA\Response(response: 200, content: new OA\JsonContent(ref: new Model(type: \ApiKit\OpenApi\Schema\EnvelopeSchemas::class)))]
```

Verify with `bin/console nelmio:apidoc:dump` (or open `/api/doc.json`) that **both**
`ErrorEnvelope` and `ValidationErrorEnvelope` show up under `components.schemas` —
`EnvelopeSchemas` carries two `#[OA\Schema]` attributes on one class, and how many of them a
given `nelmio` version picks up per `#[Model]` reference isn't guaranteed by their public docs.
If only one appears, fall back to Option A for the missing one.

Either option is a one-time setup per project, not something ApiKit can do for you — see
["No processor, no listener"](#what-this-deliberately-does-not-do) above for why.

## Requirements

- PHP 8.2+
- `zircote/swagger-php` ^6.0 — every attribute here extends `OpenApi\Attributes\Response`
  (tested against 6.4)
- `nelmio/api-doc-bundle` ^5.0 — `ApiSuccessResponse`/`ApiCreatedResponse` use its `#[Model]`
  attribute to describe `data` (tested against 5.9/5.10)

Both are declared as `require-dev`/`suggest` on ApiKit itself, never `require`.
