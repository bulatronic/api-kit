<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\OpenApi\Attribute;

use ApiKit\Tests\Support\OpenApiTestCase;

/**
 * @covers \ApiKit\OpenApi\Attribute\ApiNoContentResponse
 */
final class ApiNoContentResponseTest extends OpenApiTestCase
{
    /**
     * @throws \JsonException
     */
    public function testHasNoBody(): void
    {
        $doc = self::scanFixtures();
        $response = $doc['paths']['/fixtures/widgets/{id}']['delete']['responses']['204'];

        self::assertArrayNotHasKey('content', $response);
        self::assertSame('No Content', $response['description']);
    }
}
