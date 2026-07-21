<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\OpenApi\Attribute;

use ApiKit\Tests\Support\OpenApiTestCase;

/**
 * @covers \ApiKit\OpenApi\Attribute\ApiErrorResponse
 */
final class ApiErrorResponseTest extends OpenApiTestCase
{
    /**
     * @throws \JsonException
     */
    public function testLinksThePlainErrorEnvelopeByDefault(): void
    {
        $doc = self::scanFixtures();
        $schema = $doc['paths']['/fixtures/widgets/{id}']['get']['responses']['404']['content']['application/json']['schema'];

        self::assertSame('#/components/schemas/ErrorEnvelope', $schema['$ref']);
    }

    /**
     * @throws \JsonException
     */
    public function testLinksTheValidationErrorEnvelopeWhenRequested(): void
    {
        $doc = self::scanFixtures();
        $schema = $doc['paths']['/fixtures/widgets']['post']['responses']['422']['content']['application/json']['schema'];

        self::assertSame('#/components/schemas/ValidationErrorEnvelope', $schema['$ref']);
    }
}
