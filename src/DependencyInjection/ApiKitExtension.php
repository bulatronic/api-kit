<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection;

use ApiKit\Validator\Constraint\DoctrineEntityExistenceChecker;
use ApiKit\Validator\Constraint\EntityExistenceCheckerInterface;
use ApiKit\Validator\Constraint\EntityExistsValidator;
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
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Register EntityExists infrastructure only if doctrine/orm is installed.
        // String-based interface_exists avoids a static reference to Doctrine classes,
        // so PHPStan/Psalm will not complain about an undefined class in the bundle itself.
        if (\interface_exists('Doctrine\\ORM\\EntityManagerInterface')) {
            $checkerDef = new Definition(DoctrineEntityExistenceChecker::class);
            $checkerDef->setAutowired(true);
            $container->setDefinition(DoctrineEntityExistenceChecker::class, $checkerDef);
            $container->setAlias(EntityExistenceCheckerInterface::class, DoctrineEntityExistenceChecker::class);

            $validatorDef = new Definition(EntityExistsValidator::class);
            $validatorDef->setAutowired(true);
            $validatorDef->addTag('validator.constraint_validator');
            $container->setDefinition(EntityExistsValidator::class, $validatorDef);
        }
    }

    public function getAlias(): string
    {
        return 'api_kit';
    }
}
