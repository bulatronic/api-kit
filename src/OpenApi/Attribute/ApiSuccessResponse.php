<?php

declare(strict_types=1);

namespace ApiKit\OpenApi\Attribute;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Documents a success envelope response: { success: true, data: ..., meta: {...} }.
 *
 * Matches the shape produced by ApiKit\Response\ResponseFactory::success(). Use it as
 * a sibling attribute next to #[OA\Get]/#[OA\Post]/... on the same controller method —
 * swagger-php merges repeated Response-like attributes into that operation's `responses`,
 * it does not need to be nested inside `responses: [...]`.
 *
 *   #[OA\Get(path: '/api/tweets/{id}', summary: 'Get a tweet by id', tags: ['Tweets'])]
 *   #[ApiSuccessResponse(TweetDto::class)]
 *   #[ApiErrorResponse(404, 'Tweet not found')]
 *   public function __invoke(...): JsonResponse
 *
 * Set `isArray: true` for a plain list response (`data` becomes an array of `$dataType`).
 * For a single object, `data` is a `$ref` to `$dataType` (Model-described).
 *
 * Requires zircote/swagger-php + nelmio/api-doc-bundle (require-dev/suggest on ApiKit
 * itself — see composer.json). Nothing in ApiKit's own Bundle/Extension references this
 * namespace, so projects without those packages are unaffected: PHP only needs
 * OpenApi\Attributes\Response to exist once something in your own code actually
 * references #[ApiSuccessResponse].
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ApiSuccessResponse extends OA\Response
{
    /**
     * @param class-string $dataType FQCN of the DTO placed in `data` (described via Nelmio's #[Model])
     */
    public function __construct(
        string $dataType,
        int $status = 200,
        string $description = 'OK',
        bool $isArray = false,
    ) {
        $dataProperty = $isArray
            ? new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: $dataType)))
            : new OA\Property(property: 'data', ref: new Model(type: $dataType));

        parent::__construct(
            response: $status,
            description: $description,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                $dataProperty,
                new OA\Property(property: 'meta', type: 'object', nullable: true),
            ]),
        );
    }
}
