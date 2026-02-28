<?php

declare(strict_types=1);

namespace ApiKit\Controller;

use ApiKit\Response\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait for simplifying work with API controllers.
 *
 * Provides convenient methods for creating standardized responses.
 * Compatible with any base class including Symfony's AbstractController.
 *
 * Usage:
 *   class MyController extends AbstractController
 *   {
 *       use ApiControllerTrait;
 *   }
 */
trait ApiControllerTrait
{
    private ResponseFactoryInterface $responseFactory;

    #[Required]
    public function setResponseFactory(ResponseFactoryInterface $responseFactory): void
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Create a successful response.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @param array<string, mixed> $meta Additional metadata
     */
    protected function respondSuccess(
        mixed $data = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return $this->responseFactory->success($data, $status, $meta);
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param string $code Error code
     * @param array<string, mixed> $details Additional details
     */
    protected function respondError(
        string $message,
        int $status = 400,
        string $code = 'ERROR',
        array $details = [],
    ): JsonResponse {
        return $this->responseFactory->error($message, $code, $status, $details);
    }

    /**
     * Create a response for a created resource (201 Created).
     *
     * @param mixed $data Created resource data
     * @param array<string, mixed> $meta Additional metadata
     */
    protected function respondCreated(mixed $data, array $meta = []): JsonResponse
    {
        return $this->responseFactory->created($data, $meta);
    }

    /**
     * Create an empty response (204 No Content).
     */
    protected function respondNoContent(): JsonResponse
    {
        return $this->responseFactory->noContent();
    }

    /**
     * Create a "not found" response (404 Not Found).
     *
     * @param string $message Error message
     */
    protected function respondNotFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->respondError($message, 404, 'NOT_FOUND');
    }

    /**
     * Create a "forbidden" response (403 Forbidden).
     *
     * @param string $message Error message
     */
    protected function respondForbidden(string $message = 'Access forbidden'): JsonResponse
    {
        return $this->respondError($message, 403, 'FORBIDDEN');
    }

    /**
     * Create an "unauthorized" response (401 Unauthorized).
     *
     * @param string $message Error message
     */
    protected function respondUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->respondError($message, 401, 'UNAUTHORIZED');
    }
}
