<?php

declare(strict_types=1);

namespace ApiKit\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Contract for creating standardized API JSON responses.
 *
 * Implement this interface to replace the bundle's default response format
 * with your own structure. Register your implementation in services.yaml:
 *
 *   ApiKit\Response\ResponseFactoryInterface:
 *       class: App\Api\MyResponseFactory
 *
 * The bundle's ExceptionListener and ApiControllerTrait will use your
 * implementation automatically, including exception-to-response conversion.
 */
interface ResponseFactoryInterface
{
    /**
     * Create a successful response.
     *
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $meta Additional metadata
     */
    public function success(mixed $data = null, int $statusCode = 200, array $meta = []): JsonResponse;

    /**
     * Create an error response.
     *
     * @param string $message Human-readable error message
     * @param string $code Machine-readable error code (e.g. VALIDATION_ERROR)
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $details Additional error details
     */
    public function error(
        string $message,
        string $code = 'ERROR',
        int $statusCode = 400,
        array $details = [],
    ): JsonResponse;

    /**
     * Create a response for a created resource (201 Created).
     *
     * @param mixed $data Created resource data
     * @param array<string, mixed> $meta Additional metadata
     */
    public function created(mixed $data, array $meta = []): JsonResponse;

    /**
     * Create an empty response (204 No Content).
     */
    public function noContent(): JsonResponse;
}
