<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\OpenApi\Attribute;

use ApiKit\OpenApi\Attribute\ApiSuccessResponse;
use ApiKit\Tests\Fixture\OpenApi\Dto\WidgetDto;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes\JsonContent;
use PHPUnit\Framework\TestCase;

/**
 * Asserts on the attribute's own PHP object graph rather than a scanned OpenAPI document:
 * `data`'s `ref` is a `Nelmio\ApiDocBundle\Attribute\Model` instance, and that only resolves to
 * a real `$ref` string inside Nelmio's own container-backed pipeline (`ModelRegister`) — a bare
 * `swagger-php` `Generator` scan (no Symfony container) cannot resolve it and throws. See
 * `WidgetFixtureController` for why the fixture controller deliberately avoids this attribute.
 *
 * @covers \ApiKit\OpenApi\Attribute\ApiSuccessResponse
 */
final class ApiSuccessResponseTest extends TestCase
{
    public function testSingleObjectDataIsARefToTheModel(): void
    {
        $response = new ApiSuccessResponse(WidgetDto::class);

        self::assertSame(200, $response->response);
        self::assertSame('OK', $response->description);

        $content = $response->_unmerged[0];
        self::assertInstanceOf(JsonContent::class, $content);

        [$success, $data, $meta] = $content->properties;

        self::assertSame('success', $success->property);
        self::assertSame('boolean', $success->type);
        self::assertTrue($success->example);

        self::assertSame('data', $data->property);
        self::assertInstanceOf(Model::class, $data->ref);
        self::assertSame(WidgetDto::class, $data->ref->type);

        self::assertSame('meta', $meta->property);
        self::assertSame('object', $meta->type);
        self::assertTrue($meta->nullable);
    }

    public function testIsArrayWrapsDataInAnArrayOfRefs(): void
    {
        $response = new ApiSuccessResponse(WidgetDto::class, isArray: true);
        $content = $response->_unmerged[0];

        [, $data] = $content->properties;

        self::assertSame('array', $data->type);
        self::assertInstanceOf(Model::class, $data->items->ref);
        self::assertSame(WidgetDto::class, $data->items->ref->type);
    }
}
