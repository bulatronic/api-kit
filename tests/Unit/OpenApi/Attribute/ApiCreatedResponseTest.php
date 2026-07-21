<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\OpenApi\Attribute;

use ApiKit\OpenApi\Attribute\ApiCreatedResponse;
use ApiKit\Tests\Fixture\OpenApi\Dto\WidgetDto;
use Nelmio\ApiDocBundle\Attribute\Model;
use PHPUnit\Framework\TestCase;

/**
 * Asserts on the attribute's own PHP object graph — see {@see ApiSuccessResponseTest} for why.
 *
 * @covers \ApiKit\OpenApi\Attribute\ApiCreatedResponse
 */
final class ApiCreatedResponseTest extends TestCase
{
    public function testDefaultsToStatus201WithTheSuccessEnvelope(): void
    {
        $response = new ApiCreatedResponse(WidgetDto::class);

        self::assertSame(201, $response->response);
        self::assertSame('Created', $response->description);

        $content = $response->_unmerged[0];
        [$success, $data] = $content->properties;

        self::assertTrue($success->example);
        self::assertSame('data', $data->property);
        self::assertInstanceOf(Model::class, $data->ref);
        self::assertSame(WidgetDto::class, $data->ref->type);
    }
}
