<?php

declare(strict_types=1);

namespace ApiKit\Tests\Support;

use OpenApi\Generator;
use PHPUnit\Framework\TestCase;

/**
 * Base class for OpenAPI attribute/schema tests: scans src/OpenApi together with the
 * test fixture controllers via swagger-php's own Generator, and hands back the
 * resulting OpenAPI document as a plain array so assertions can use simple array
 * access instead of walking swagger-php's object graph.
 */
abstract class OpenApiTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     * @throws \JsonException
     */
    protected static function scanFixtures(): array
    {
        $root = \dirname(__DIR__, 2);

        $openapi = new Generator()->generate([
            $root . '/src/OpenApi/Schema',
            $root . '/tests/Fixture/OpenApi',
        ]);

        self::assertNotNull($openapi, 'swagger-php failed to produce an OpenApi document from the fixtures.');

        /** @var array<string, mixed> $decoded */
        $decoded = \json_decode($openapi->toJson(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
