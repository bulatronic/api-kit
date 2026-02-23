<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\Response;

use ApiKit\Response\ResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ApiKit\Response\ResponseFactory
 */
final class ResponseFactoryTest extends TestCase
{
    private ResponseFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ResponseFactory([
            'include_timestamp' => true,
            'pretty_print' => false,
        ]);
    }

    public function testSuccessResponseStructure(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $response = $this->factory->success($data);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->contains('Content-Type', 'application/json'));

        $content = $response->getContent();
        self::assertNotFalse($content);
        $decoded = \json_decode($content, true);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['success']);
        self::assertSame($data, $decoded['data']);
        self::assertArrayHasKey('meta', $decoded);
        self::assertArrayHasKey('timestamp', $decoded['meta']);
    }

    public function testSuccessWithCustomStatusCode(): void
    {
        $response = $this->factory->success(['message' => 'ok'], 201);

        self::assertSame(201, $response->getStatusCode());
    }

    public function testSuccessWithMeta(): void
    {
        $meta = ['custom' => 'value'];
        $response = $this->factory->success(null, 200, $meta);

        $content = $response->getContent();
        self::assertNotFalse($content);
        $decoded = \json_decode($content, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('custom', $decoded['meta']);
        self::assertSame('value', $decoded['meta']['custom']);
    }

    public function testErrorResponseStructure(): void
    {
        $response = $this->factory->error('Something went wrong', 'ERROR_CODE', 400);

        self::assertSame(400, $response->getStatusCode());

        $content = $response->getContent();
        self::assertNotFalse($content);
        $decoded = \json_decode($content, true);
        self::assertIsArray($decoded);
        self::assertFalse($decoded['success']);
        self::assertArrayHasKey('error', $decoded);
        self::assertSame('ERROR_CODE', $decoded['error']['code']);
        self::assertSame('Something went wrong', $decoded['error']['message']);
    }

    public function testErrorWithDetails(): void
    {
        $details = ['field' => 'email', 'reason' => 'invalid'];
        $response = $this->factory->error('Error', 'VALIDATION_ERROR', 422, $details);

        $content = $response->getContent();
        self::assertNotFalse($content);
        $decoded = \json_decode($content, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('details', $decoded['error']);
        self::assertSame($details, $decoded['error']['details']);
    }

    public function testCreatedResponse(): void
    {
        $data = ['id' => 123, 'name' => 'Created'];
        $response = $this->factory->created($data);

        self::assertSame(201, $response->getStatusCode());

        $content = $response->getContent();
        self::assertNotFalse($content);
        $decoded = \json_decode($content, true);
        self::assertIsArray($decoded);
        self::assertTrue($decoded['success']);
        self::assertSame($data, $decoded['data']);
    }

    public function testNoContentResponse(): void
    {
        $response = $this->factory->noContent();

        self::assertSame(204, $response->getStatusCode());
        // JsonResponse(null, 204) creates an empty JSON object
        self::assertSame('{}', $response->getContent());
    }
}
