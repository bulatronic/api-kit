<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection;

use ApiKit\Validator\Constraint\EntityExistsValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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

        // Register EntityExistsValidator only if doctrine/orm is installed.
        // Checked via interface_exists (no service lookup needed — runs before
        // any CompilerPass, so the validator.constraint_validator locator will
        // include this service when Symfony builds it later).
        if (\interface_exists(EntityManagerInterface::class)) {
            $definition = new Definition(EntityExistsValidator::class);
            $definition->setAutowired(true);
            $definition->addTag('validator.constraint_validator');
            $container->setDefinition(EntityExistsValidator::class, $definition);
        }
    }

    public function getAlias(): string
    {
        return 'api_kit';
    }
}
