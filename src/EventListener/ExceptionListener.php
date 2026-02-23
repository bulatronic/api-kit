<?php

declare(strict_types=1);

namespace ApiKit\EventListener;

use ApiKit\Exception\ApiException;
use ApiKit\Response\ResponseFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Listener for handling exceptions and transforming them into standardized JSON responses.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final readonly class ExceptionListener
{
    /**
     * @param array<string, mixed> $exceptionConfig
     */
    public function __construct(
        private ResponseFactory $responseFactory,
        private LoggerInterface $logger,
        private array $exceptionConfig = [],
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof HttpExceptionInterface => $this->handleHttpException($exception),
            $exception instanceof ValidationFailedException => $this->buildValidationResponse($exception, 422),
            default => $this->handleGenericException($exception),
        };

        // Log only server-side errors (5xx); 4xx are client errors, not ours
        if ($this->shouldLogError($response->getStatusCode())) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }

        $event->setResponse($response);
    }

    /**
     * Handle HttpExceptionInterface.
     * - ApiException: use its structured details
     * - HttpException wrapping ValidationFailedException: extract violations
     * - Everything else: build generic error
     */
    private function handleHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ValidationFailedException) {
            return $this->buildValidationResponse($previous, $exception->getStatusCode());
        }

        $statusCode = $exception->getStatusCode();
        $code = $this->getErrorCodeFromStatusCode($statusCode);

        // ApiException carries intentional structured details — use them as-is
        if ($exception instanceof ApiException) {
            return $this->responseFactory->error(
                message: $exception->getMessage() ?: $this->getDefaultMessageForStatus($statusCode),
                code: $code,
                statusCode: $statusCode,
                details: $exception->getDetails(),
            );
        }

        $details = [];
        if ($this->shouldShowTrace()) {
            $details['trace'] = $exception->getTraceAsString();
        }

        return $this->responseFactory->error(
            message: $exception->getMessage() ?: $this->getDefaultMessageForStatus($statusCode),
            code: $code,
            statusCode: $statusCode,
            details: $details,
        );
    }

    /**
     * Build a structured validation error response from a ValidationFailedException.
     * Used both for direct ValidationFailedException and when wrapped inside HttpException.
     */
    private function buildValidationResponse(ValidationFailedException $exception, int $statusCode): JsonResponse
    {
        $violations = [];
        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath() ?: null,
                'message' => $violation->getMessage(),
            ];
        }

        return $this->responseFactory->error(
            message: 'Validation error',
            code: 'VALIDATION_ERROR',
            statusCode: $statusCode,
            details: ['violations' => $violations],
        );
    }

    /**
     * Handle generic (non-HTTP) exceptions — always results in 500.
     */
    private function handleGenericException(\Throwable $exception): JsonResponse
    {
        $details = [];

        if ($this->shouldShowTrace()) {
            $details['exception'] = $exception::class;
            $details['file'] = $exception->getFile();
            $details['line'] = $exception->getLine();
            $details['trace'] = $exception->getTraceAsString();
        }

        return $this->responseFactory->error(
            message: 'Internal server error',
            code: 'INTERNAL_SERVER_ERROR',
            statusCode: 500,
            details: $details,
        );
    }

    /**
     * Get error code from HTTP status code.
     */
    private function getErrorCodeFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'HTTP_ERROR_' . $statusCode,
        };
    }

    /**
     * Get a default human-readable message for a given HTTP status code.
     */
    private function getDefaultMessageForStatus(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'HTTP Error ' . $statusCode,
        };
    }

    /**
     * Only log server-side errors (5xx) unless configured otherwise.
     */
    private function shouldLogError(int $statusCode): bool
    {
        if (!($this->exceptionConfig['log_errors'] ?? true)) {
            return false;
        }

        return $statusCode >= 500;
    }

    private function shouldShowTrace(): bool
    {
        return $this->exceptionConfig['show_trace'] ?? false;
    }
}
