<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\Controller;

use ApiKit\Controller\ApiControllerTrait;
use ApiKit\Response\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @covers \ApiKit\Controller\ApiControllerTrait
 */
final class ApiControllerTraitTest extends TestCase
{
    private ConcreteController $controller;

    protected function setUp(): void
    {
        $factory = new ResponseFactory([
            'include_timestamp' => false,
            'pretty_print' => false,
        ]);

        $this->controller = new ConcreteController();
        // Verify that the #[Required] setter works directly
        $this->controller->setResponseFactory($factory);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(JsonResponse $response): array
    {
        $content = $response->getContent();
        self::assertNotFalse($content);

        return \json_decode($content, true);
    }

    // -------------------------------------------------------------------------
    // respondSuccess
    // -------------------------------------------------------------------------

    public function testRespondSuccess(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $response = $this->controller->callRespondSuccess($data);

        self::assertSame(200, $response->getStatusCode());
        $body = self::decode($response);
        self::assertTrue($body['success']);
        self::assertSame($data, $body['data']);
    }

    public function testRespondSuccessWithCustomStatus(): void
    {
        $response = $this->controller->callRespondSuccess(null, 202);

        self::assertSame(202, $response->getStatusCode());
    }

    public function testRespondSuccessWithMeta(): void
    {
        $response = $this->controller->callRespondSuccess(['x' => 1], meta: ['page' => 2]);

        $body = self::decode($response);
        self::assertSame(2, $body['meta']['page']);
    }

    // -------------------------------------------------------------------------
    // respondError
    // -------------------------------------------------------------------------

    public function testRespondError(): void
    {
        $response = $this->controller->callRespondError('Something went wrong', 400, 'BAD_INPUT');

        self::assertSame(400, $response->getStatusCode());
        $body = self::decode($response);
        self::assertFalse($body['success']);
        self::assertSame('BAD_INPUT', $body['error']['code']);
        self::assertSame('Something went wrong', $body['error']['message']);
    }

    public function testRespondErrorWithDetails(): void
    {
        $details = ['field' => 'email'];
        $response = $this->controller->callRespondError('Invalid', 422, 'INVALID', $details);

        $body = self::decode($response);
        self::assertSame($details, $body['error']['details']);
    }

    // -------------------------------------------------------------------------
    // respondCreated
    // -------------------------------------------------------------------------

    public function testRespondCreated(): void
    {
        $data = ['id' => 42];
        $response = $this->controller->callRespondCreated($data);

        self::assertSame(201, $response->getStatusCode());
        $body = self::decode($response);
        self::assertTrue($body['success']);
        self::assertSame($data, $body['data']);
    }

    // -------------------------------------------------------------------------
    // respondNoContent
    // -------------------------------------------------------------------------

    public function testRespondNoContent(): void
    {
        $response = $this->controller->callRespondNoContent();

        self::assertSame(204, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Shortcut helpers
    // -------------------------------------------------------------------------

    public function testRespondNotFound(): void
    {
        $response = $this->controller->callRespondNotFound();

        self::assertSame(404, $response->getStatusCode());
        $body = self::decode($response);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testRespondNotFoundWithCustomMessage(): void
    {
        $response = $this->controller->callRespondNotFound('User not found');

        $body = self::decode($response);
        self::assertSame('User not found', $body['error']['message']);
    }

    public function testRespondForbidden(): void
    {
        $response = $this->controller->callRespondForbidden();

        self::assertSame(403, $response->getStatusCode());
        $body = self::decode($response);
        self::assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testRespondUnauthorized(): void
    {
        $response = $this->controller->callRespondUnauthorized();

        self::assertSame(401, $response->getStatusCode());
        $body = self::decode($response);
        self::assertSame('UNAUTHORIZED', $body['error']['code']);
    }

    // -------------------------------------------------------------------------
    // Compatibility: setResponseFactory is public (for DI via #[Required])
    // -------------------------------------------------------------------------

    public function testSetResponseFactoryIsPublic(): void
    {
        $method = new \ReflectionMethod(ConcreteController::class, 'setResponseFactory');

        self::assertTrue($method->isPublic());
    }

    public function testTraitCanBeUsedAlongsideOtherBaseClasses(): void
    {
        // Verify that the trait does not require a specific base class
        $controller = new ConcreteControllerWithParent();
        $controller->setResponseFactory(new ResponseFactory(['include_timestamp' => false]));

        $response = $controller->callRespondSuccess(['ok' => true]);

        self::assertSame(200, $response->getStatusCode());
    }
}

// -------------------------------------------------------------------------
// Helper classes
// -------------------------------------------------------------------------

/**
 * Controller without a base class — basic scenario.
 */
final class ConcreteController
{
    use ApiControllerTrait;

    /** @param array<string, mixed> $meta */
    public function callRespondSuccess(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return $this->respondSuccess($data, $status, $meta);
    }

    /** @param array<string, mixed> $details */
    public function callRespondError(string $message, int $status = 400, string $code = 'ERROR', array $details = []): JsonResponse
    {
        return $this->respondError($message, $status, $code, $details);
    }

    /** @param array<string, mixed> $meta */
    public function callRespondCreated(mixed $data, array $meta = []): JsonResponse
    {
        return $this->respondCreated($data, $meta);
    }

    public function callRespondNoContent(): JsonResponse
    {
        return $this->respondNoContent();
    }

    public function callRespondNotFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->respondNotFound($message);
    }

    public function callRespondForbidden(string $message = 'Access forbidden'): JsonResponse
    {
        return $this->respondForbidden($message);
    }

    public function callRespondUnauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->respondUnauthorized($message);
    }
}

/**
 * Controller with an arbitrary base class — compatibility check.
 */
abstract class SomeBaseController
{
    protected function someBaseMethod(): string
    {
        return 'base';
    }
}

final class ConcreteControllerWithParent extends SomeBaseController
{
    use ApiControllerTrait;

    public function callRespondSuccess(mixed $data = null): JsonResponse
    {
        return $this->respondSuccess($data);
    }
}
