<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\OpenApi\Schema;

use ApiKit\Tests\Support\OpenApiTestCase;

/**
 * @covers \ApiKit\OpenApi\Schema\EnvelopeSchemas
 */
final class EnvelopeSchemasTest extends OpenApiTestCase
{
    /**
     * @throws \JsonException
     */
    public function testErrorEnvelopeMatchesTheResponseFactoryShape(): void
    {
        $doc = self::scanFixtures();
        $schema = $doc['components']['schemas']['ErrorEnvelope'];

        self::assertFalse($schema['properties']['success']['example']);
        self::assertSame(['success', 'error'], $schema['required']);
        self::assertSame(['code', 'message'], $schema['properties']['error']['required']);
        self::assertSame('string', $schema['properties']['error']['properties']['code']['type']);
        self::assertSame('string', $schema['properties']['error']['properties']['message']['type']);
        self::assertSame('object', $schema['properties']['error']['properties']['details']['type']);
        self::assertTrue($schema['properties']['error']['properties']['details']['nullable']);
    }

    /**
     * @throws \JsonException
     */
    public function testValidationErrorEnvelopeAddsViolationsUnderErrorDetails(): void
    {
        $doc = self::scanFixtures();
        $schema = $doc['components']['schemas']['ValidationErrorEnvelope'];

        self::assertSame('#/components/schemas/ErrorEnvelope', $schema['allOf'][0]['$ref']);

        $violations = $schema['allOf'][1]['properties']['error']['properties']['details']['properties']['violations'];
        self::assertSame('array', $violations['type']);
        self::assertSame('string', $violations['items']['properties']['field']['type']);
        self::assertTrue($violations['items']['properties']['field']['nullable']);
        self::assertSame('string', $violations['items']['properties']['message']['type']);
    }
}
