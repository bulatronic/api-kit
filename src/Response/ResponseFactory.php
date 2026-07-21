<?php

declare(strict_types=1);

namespace ApiKit\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Default factory for creating standardized API JSON responses.
 *
 * Extend this class to add project-specific response methods (e.g. paginated()),
 * or implement {@see ResponseFactoryInterface} from scratch to replace the
 * response format entirely.
 */
readonly class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * @param array<string, mixed> $responseConfig
     */
    public function __construct(
        private array $responseConfig = [],
    ) {
    }

    /**
     * Create a successful response.
     *
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $meta Additional metadata
     */
    public function success(
        mixed $data = null,
        int $statusCode = 200,
        array $meta = [],
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($this->shouldIncludeTimestamp()) {
            $meta['timestamp'] = new \DateTimeImmutable()->format(\DateTimeInterface::ATOM);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $this->createJsonResponse($response, $statusCode);
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message
     * @param string $code Error code
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $details Additional error details
     */
    public function error(
        string $message,
        string $code = 'ERROR',
        int $statusCode = 400,
        array $details = [],
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if (!empty($details)) {
            $response['error']['details'] = $details;
        }

        return $this->createJsonResponse($response, $statusCode);
    }

    /**
     * Create a response for a created resource.
     *
     * @param mixed $data Created resource data
     * @param array<string, mixed> $meta Additional metadata
     */
    public function created(mixed $data, array $meta = []): JsonResponse
    {
        return $this->success($data, 201, $meta);
    }

    /**
     * Create an empty response (e.g., for DELETE).
     */
    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * Create a JsonResponse with configuration applied.
     *
     * @param array<string, mixed> $data
     */
    private function createJsonResponse(array $data, int $statusCode): JsonResponse
    {
        $response = new JsonResponse($data, $statusCode);

        if ($this->shouldPrettyPrint()) {
            $response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRETTY_PRINT);
        }

        return $response;
    }

    private function shouldIncludeTimestamp(): bool
    {
        return $this->responseConfig['include_timestamp'] ?? true;
    }

    private function shouldPrettyPrint(): bool
    {
        return $this->responseConfig['pretty_print'] ?? false;
    }
}
