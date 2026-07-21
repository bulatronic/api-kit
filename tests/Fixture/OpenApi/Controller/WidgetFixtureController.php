<?php

declare(strict_types=1);

namespace ApiKit\Tests\Fixture\OpenApi\Controller;

use ApiKit\OpenApi\Attribute\ApiErrorResponse;
use ApiKit\OpenApi\Attribute\ApiNoContentResponse;
use OpenApi\Attributes as OA;

/**
 * Not a real Symfony controller — a pure scan target for
 * (new OpenApi\Generator())->generate() in the attribute unit tests.
 *
 * Deliberately does NOT use #[ApiSuccessResponse]/#[ApiCreatedResponse] here: those build a
 * `data` ref via Nelmio's `#[Model]`, which only resolves to a real `$ref` string inside
 * Nelmio's own pipeline (`ModelRegister`, wired up by the `NelmioApiDocBundle` container) — a
 * bare `(new OpenApi\Generator())->generate())` scan (no Symfony container involved) cannot
 * resolve it and throws. `ApiSuccessResponseTest`/`ApiCreatedResponseTest` therefore assert on
 * those two attributes directly, without going through a Generator scan at all — see their
 * test classes for details.
 */
final class WidgetFixtureController
{
    #[OA\Get(path: '/fixtures/widgets/{id}', summary: 'Get a single widget', tags: ['Fixtures'])]
    #[ApiErrorResponse(404, 'Widget not found')]
    public function get(): void
    {
    }

    #[OA\Post(path: '/fixtures/widgets', summary: 'Create a widget', tags: ['Fixtures'])]
    #[ApiErrorResponse(422, 'Validation failed', isValidation: true)]
    public function create(): void
    {
    }

    #[OA\Delete(path: '/fixtures/widgets/{id}', summary: 'Delete a widget', tags: ['Fixtures'])]
    #[ApiNoContentResponse]
    public function delete(): void
    {
    }
}
