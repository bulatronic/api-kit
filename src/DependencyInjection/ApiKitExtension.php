<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Extension for loading ApiKit configuration.
 */
final class ApiKitExtension extends Extension
{
    /**
     * @param array<int, mixed> $configs
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register configuration parameters
        $container->setParameter('api_kit.response', $config['response']);
        $container->setParameter('api_kit.validation', $config['validation']);
        $container->setParameter('api_kit.exception_handling', $config['exception_handling']);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');
    }

    public function getAlias(): string
    {
        return 'api_kit';
    }
}
