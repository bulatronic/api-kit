<?php

declare(strict_types=1);

namespace ApiKit;

use ApiKit\DependencyInjection\Compiler\RegisterDoctrineCheckerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * ApiKit Bundle for creating REST APIs with DTO validation,
 * standardized responses and thin controllers.
 */
final class ApiKitBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new RegisterDoctrineCheckerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
