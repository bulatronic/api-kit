<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\EventListener;

use ApiKit\EventListener\ExceptionListener;
use ApiKit\Exception\ApiException;
use ApiKit\Response\ResponseFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * @covers \ApiKit\EventListener\ExceptionListener
 * @uses \ApiKit\Response\ResponseFactory
 */
final class ExceptionListenerTest extends TestCase
{
    private ExceptionListener $listener;
    private ResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ResponseFactory([
            'include_timestamp' => false,
            'pretty_print' => false,
        ]);

        // createStub — not verifying calls; tests that need expectations create their own mock
        $this->listener = new ExceptionListener(
            $this->factory,
            $this->createStub(LoggerInterface::class),
            ['log_errors' => true, 'show_trace' => false],
        );
    }

    private function makeEvent(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }

    private static function getResponse(ExceptionEvent $event): \Symfony\Component\HttpFoundation\Response
    {
        $response = $event->getResponse();
        self::assertNotNull($response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(ExceptionEvent $event): array
    {
        $content = self::getResponse($event)->getContent();
        self::assertNotFalse($content);

        return \json_decode($content, true);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{ExceptionListener, MockObject&LoggerInterface}
     */
    private function makeListenerWithMockLogger(array $config = ['log_errors' => true, 'show_trace' => false]): array
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $listener = new ExceptionListener($this->factory, $logger, $config);

        return [$listener, $logger];
    }

    // -------------------------------------------------------------------------
    // 4xx — HTTP exceptions
    // -------------------------------------------------------------------------

    public function testHandles404(): void
    {
        $event = $this->makeEvent(new NotFoundHttpException('Page not found'));
        $this->listener->onKernelException($event);

        self::assertSame(404, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertFalse($data['success']);
        self::assertSame('NOT_FOUND', $data['error']['code']);
        self::assertSame('Page not found', $data['error']['message']);
    }

    public function testHandles400(): void
    {
        $event = $this->makeEvent(new BadRequestHttpException('Bad input'));
        $this->listener->onKernelException($event);

        self::assertSame(400, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('BAD_REQUEST', $data['error']['code']);
    }

    public function testHandles401(): void
    {
        $event = $this->makeEvent(new UnauthorizedHttpException('Bearer'));
        $this->listener->onKernelException($event);

        self::assertSame(401, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('UNAUTHORIZED', $data['error']['code']);
    }

    public function testHandles409(): void
    {
        $event = $this->makeEvent(new ConflictHttpException('Already exists'));
        $this->listener->onKernelException($event);

        self::assertSame(409, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('CONFLICT', $data['error']['code']);
    }

    public function testHandlesUnknown4xxWithGenericCode(): void
    {
        $event = $this->makeEvent(new HttpException(418, "I'm a teapot"));
        $this->listener->onKernelException($event);

        self::assertSame(418, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('HTTP_ERROR_418', $data['error']['code']);
    }

    public function testUsesDefaultMessageWhenHttpExceptionMessageIsEmpty(): void
    {
        $event = $this->makeEvent(new NotFoundHttpException());
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertSame('Not Found', $data['error']['message']);
    }

    // -------------------------------------------------------------------------
    // ValidationFailedException — direct and wrapped in HttpException
    // -------------------------------------------------------------------------

    public function testHandlesDirectValidationFailedException(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Cannot be blank', null, [], null, 'name', ''),
            new ConstraintViolation('Too short', null, [], null, 'password', '123'),
        ]);

        $event = $this->makeEvent(new ValidationFailedException(new \stdClass(), $violations));
        $this->listener->onKernelException($event);

        self::assertSame(422, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertCount(2, $data['error']['details']['violations']);
        self::assertSame('name', $data['error']['details']['violations'][0]['field']);
        self::assertSame('Cannot be blank', $data['error']['details']['violations'][0]['message']);
        self::assertSame('password', $data['error']['details']['violations'][1]['field']);
    }

    public function testHandlesValidationFailedExceptionWrappedInHttpException(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Invalid value', null, [], null, 'email', 'bad'),
        ]);

        $event = $this->makeEvent(new UnprocessableEntityHttpException(
            'Validation failed',
            new ValidationFailedException(new \stdClass(), $violations),
        ));
        $this->listener->onKernelException($event);

        self::assertSame(422, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertCount(1, $data['error']['details']['violations']);
        self::assertSame('email', $data['error']['details']['violations'][0]['field']);
    }

    public function testValidationWrappedIn400PreservesStatusCode(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Wrong type', null, [], null, 'age', 'abc'),
        ]);

        $event = $this->makeEvent(new BadRequestHttpException(
            '',
            new ValidationFailedException(new \stdClass(), $violations),
        ));
        $this->listener->onKernelException($event);

        self::assertSame(400, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
    }

    public function testViolationWithEmptyPropertyPathHasNullField(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Global error', null, [], null, '', null),
        ]);

        $event = $this->makeEvent(new ValidationFailedException(new \stdClass(), $violations));
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertNull($data['error']['details']['violations'][0]['field']);
    }

    public function testInvalidValueIsNotExposedInViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Too weak', null, [], null, 'password', 'secret123'),
        ]);

        $event = $this->makeEvent(new ValidationFailedException(new \stdClass(), $violations));
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertArrayNotHasKey('invalid_value', $data['error']['details']['violations'][0]);
    }

    // -------------------------------------------------------------------------
    // ApiException — structured details
    // -------------------------------------------------------------------------

    public function testApiExceptionDetailsAreIncludedInResponse(): void
    {
        $details = ['field' => 'email', 'reason' => 'already_taken'];
        $event = $this->makeEvent(new ApiException(409, 'Conflict', $details));
        $this->listener->onKernelException($event);

        self::assertSame(409, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertFalse($data['success']);
        self::assertSame('CONFLICT', $data['error']['code']);
        self::assertSame('Conflict', $data['error']['message']);
        self::assertSame($details, $data['error']['details']);
    }

    public function testApiExceptionWithEmptyDetailsProducesNoDetails(): void
    {
        $event = $this->makeEvent(new ApiException(404, 'Not found'));
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertArrayNotHasKey('details', $data['error']);
    }

    public function testApiExceptionUsesDefaultMessageWhenEmpty(): void
    {
        $event = $this->makeEvent(new ApiException(503));
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertSame('Service Unavailable', $data['error']['message']);
    }

    public function testApiExceptionWithWrappedValidationIsHandledAsValidation(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Cannot be blank', null, [], null, 'title', ''),
        ]);

        // ApiException wrapping ValidationFailedException should still extract violations
        $event = $this->makeEvent(new ApiException(
            422,
            'Validation failed',
            [],
            new ValidationFailedException(new \stdClass(), $violations),
        ));
        $this->listener->onKernelException($event);

        self::assertSame(422, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertSame('VALIDATION_ERROR', $data['error']['code']);
        self::assertCount(1, $data['error']['details']['violations']);
    }

    // -------------------------------------------------------------------------
    // Generic exceptions → 500
    // -------------------------------------------------------------------------

    public function testHandlesGenericException(): void
    {
        $event = $this->makeEvent(new \RuntimeException('Something broke'));
        $this->listener->onKernelException($event);

        self::assertSame(500, self::getResponse($event)->getStatusCode());
        $data = self::decode($event);
        self::assertFalse($data['success']);
        self::assertSame('INTERNAL_SERVER_ERROR', $data['error']['code']);
    }

    // -------------------------------------------------------------------------
    // Logging: only 5xx are logged as error
    // -------------------------------------------------------------------------

    public function testLogsServerErrorsOnly(): void
    {
        [$listener, $logger] = $this->makeListenerWithMockLogger();
        $logger->expects(self::once())->method('error');

        $event = $this->makeEvent(new \RuntimeException('DB down'));
        $listener->onKernelException($event);
    }

    public function testDoesNotLog4xxErrors(): void
    {
        [$listener, $logger] = $this->makeListenerWithMockLogger();
        $logger->expects(self::never())->method('error');

        $event = $this->makeEvent(new NotFoundHttpException('not found'));
        $listener->onKernelException($event);
    }

    public function testLogging5xxHttpException(): void
    {
        [$listener, $logger] = $this->makeListenerWithMockLogger();
        $logger->expects(self::once())->method('error');

        $event = $this->makeEvent(new HttpException(500, 'Server exploded'));
        $listener->onKernelException($event);
    }

    public function testDoesNotLogWhenLogErrorsDisabled(): void
    {
        [$listener, $logger] = $this->makeListenerWithMockLogger(['log_errors' => false, 'show_trace' => false]);
        $logger->expects(self::never())->method('error');

        $event = $this->makeEvent(new \RuntimeException('Silent error'));
        $listener->onKernelException($event);
    }

    // -------------------------------------------------------------------------
    // show_trace
    // -------------------------------------------------------------------------

    public function testTraceNotIncludedByDefault(): void
    {
        $event = $this->makeEvent(new \RuntimeException('Oops'));
        $this->listener->onKernelException($event);

        $data = self::decode($event);
        self::assertArrayNotHasKey('details', $data['error']);
    }

    public function testTraceIncludedWhenEnabled(): void
    {
        $listener = new ExceptionListener(
            $this->factory,
            $this->createStub(LoggerInterface::class),
            ['log_errors' => false, 'show_trace' => true],
        );

        $event = $this->makeEvent(new \RuntimeException('Traced'));
        $listener->onKernelException($event);

        $data = self::decode($event);
        self::assertArrayHasKey('details', $data['error']);
        self::assertArrayHasKey('trace', $data['error']['details']);
    }
}
