<?php

declare(strict_types=1);

namespace ApiKit;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * ApiKit Bundle for creating REST APIs with DTO validation,
 * standardized responses and thin controllers.
 */
final class ApiKitBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
