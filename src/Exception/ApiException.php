<?php

declare(strict_types=1);

namespace ApiKit\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * HTTP exception with structured error details.
 *
 * Use instead of Symfony's HttpException when you need to pass
 * machine-readable details alongside the error response.
 * The ExceptionListener automatically includes details in the JSON response.
 *
 * @example Simple usage:
 *   throw new ApiException(409, 'Email already taken');
 *
 * @example With structured details:
 *   throw new ApiException(409, 'Conflict', [
 *       'field'  => 'email',
 *       'reason' => 'already_taken',
 *   ]);
 *
 * @example From a service (details carry domain context):
 *   throw new ApiException(423, 'Account locked', [
 *       'locked_until'   => $lockedUntil->format(\DateTimeInterface::ATOM),
 *       'attempts_left'  => 0,
 *   ]);
 */
final class ApiException extends HttpException
{
    /**
     * @param int $statusCode HTTP status code
     * @param string $message Human-readable error message
     * @param array<string, mixed> $details Structured details included in the error response
     * @param \Throwable|null $previous Previous exception
     * @param array<string, string> $headers Additional HTTP headers
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        private readonly array $details = [],
        ?\Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
