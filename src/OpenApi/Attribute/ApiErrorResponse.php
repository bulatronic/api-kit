<?php

declare(strict_types=1);

namespace ApiKit\OpenApi\Attribute;

use OpenApi\Attributes as OA;

/**
 * Documents an error envelope response, linking to the reusable `ErrorEnvelope` /
 * `ValidationErrorEnvelope` schemas from {@see \ApiKit\OpenApi\Schema\EnvelopeSchemas}.
 *
 * Matches the shape produced by ApiKit\Response\ResponseFactory::error() /
 * ApiKit\EventListener\ExceptionListener for any 4xx/5xx.
 *
 * Deliberately no longer than the "lazy" version that ships with no content at all
 * (`new OA\Response(response: 404, description: '...')`) — so there is no excuse to
 * keep skipping the schema out of habit:
 *
 *   // wrong:
 *   new OA\Response(response: 404, description: 'Tweet not found'),
 *   // right:
 *   #[ApiErrorResponse(404, 'Tweet not found')]
 *
 * Pass `isValidation: true` for 422 responses to link `ValidationErrorEnvelope`
 * (adds `error.details.violations`) instead of the plain `ErrorEnvelope`.
 *
 *   #[ApiErrorResponse(422, 'Validation error', isValidation: true)]
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ApiErrorResponse extends OA\Response
{
    public function __construct(int $status, string $description, bool $isValidation = false)
    {
        parent::__construct(
            response: $status,
            description: $description,
            content: new OA\JsonContent(
                ref: '#/components/schemas/' . ($isValidation ? 'ValidationErrorEnvelope' : 'ErrorEnvelope'),
            ),
        );
    }
}
