<?php

declare(strict_types=1);

namespace ApiKit\Tests\Fixture\OpenApi\Dto;

use OpenApi\Attributes as OA;

/**
 * Minimal fixture DTO — has no relation to any real domain, exists only so
 * {@see \ApiKit\OpenApi\Attribute\ApiSuccessResponse} has a `$dataType` to point
 * `#[Model]` at in the fixture controllers used by the attribute unit tests.
 */
#[OA\Schema(description: 'Test fixture DTO.')]
final readonly class WidgetDto
{
    public function __construct(
        #[OA\Property(description: 'Widget id', format: 'uuid')]
        public string $id,
        #[OA\Property(description: 'Widget name')]
        public string $name,
    ) {
    }
}
