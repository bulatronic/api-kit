<?php

declare(strict_types=1);

namespace ApiKit\OpenApi\Schema;

use OpenApi\Attributes as OA;

/**
 * Reusable OpenAPI schemas for the ApiKit error envelope.
 *
 * This class has no behaviour — it exists purely so swagger-php discovers the
 * #[OA\Schema] attributes below and registers them under
 * `components.schemas.ErrorEnvelope` / `components.schemas.ValidationErrorEnvelope`.
 * Reference them from a controller via {@see \ApiKit\OpenApi\Attribute\ApiErrorResponse}.
 *
 * The shape mirrors exactly what ApiKit\Response\ResponseFactory::error() and
 * ApiKit\EventListener\ExceptionListener produce at runtime:
 *
 *   { "success": false, "error": { "code": string, "message": string, "details"?: object } }
 *
 * For validation failures (422), `error.details` is always `{ "violations": [{field, message}] }`
 * — see ExceptionListener::buildValidationResponse(). `field` is nullable because
 * ConstraintViolation::getPropertyPath() can be an empty string (normalised to null).
 */
#[OA\Schema(
    schema: 'ErrorEnvelope',
    description: 'Standard api-kit error envelope.',
    required: ['success', 'error'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(
            property: 'error',
            type: 'object',
            required: ['code', 'message'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'NOT_FOUND'),
                new OA\Property(property: 'message', type: 'string', example: 'Resource not found'),
                new OA\Property(
                    property: 'details',
                    type: 'object',
                    nullable: true,
                    description: 'Optional structured context, present only when the error carries details.',
                ),
            ],
        ),
    ],
)]
#[OA\Schema(
    schema: 'ValidationErrorEnvelope',
    description: 'api-kit error envelope for 422 validation failures — ErrorEnvelope with error.details.violations.',
    allOf: [
        new OA\Schema(ref: '#/components/schemas/ErrorEnvelope'),
        new OA\Schema(
            properties: [
                new OA\Property(
                    property: 'error',
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'details',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'violations',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'field', type: 'string', nullable: true, example: 'email'),
                                            new OA\Property(property: 'message', type: 'string', example: 'This value should not be blank.'),
                                        ],
                                    ),
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        ),
    ],
)]
final class EnvelopeSchemas
{
}
