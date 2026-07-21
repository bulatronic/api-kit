<?php

declare(strict_types=1);

namespace ApiKit\OpenApi\Attribute;

use OpenApi\Attributes as OA;

/**
 * Documents a 204 No Content response — the shape produced by
 * ApiKit\Response\ResponseFactory::noContent(). There is no body, so unlike
 * {@see ApiSuccessResponse} there is no `data`/`meta` to describe.
 *
 *   #[OA\Delete(path: '/api/tweets/{id}', summary: 'Delete a tweet', tags: ['Tweets'])]
 *   #[ApiNoContentResponse]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiNoContentResponse extends OA\Response
{
    public function __construct(string $description = 'No Content')
    {
        parent::__construct(response: 204, description: $description);
    }
}
