<?php

declare(strict_types=1);

namespace ApiKit\OpenApi\Attribute;

/**
 * Shortcut for {@see ApiSuccessResponse} defaulting to 201 Created — the shape produced
 * by ApiKit\Response\ResponseFactory::created().
 *
 *   #[OA\Post(path: '/api/tweets', summary: 'Create a tweet', tags: ['Tweets'])]
 *   #[ApiCreatedResponse(CreateTweetResponseDto::class)]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiCreatedResponse extends ApiSuccessResponse
{
    /**
     * @param class-string $dataType FQCN of the DTO placed in `data` (described via Nelmio's #[Model])
     */
    public function __construct(
        string $dataType,
        int $status = 201,
        string $description = 'Created',
        bool $isArray = false,
    ) {
        parent::__construct(
            dataType: $dataType,
            status: $status,
            description: $description,
            isArray: $isArray,
        );
    }
}
