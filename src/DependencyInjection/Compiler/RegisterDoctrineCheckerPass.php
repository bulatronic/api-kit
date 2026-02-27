<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection\Compiler;

use ApiKit\Validator\Constraint\DoctrineEntityExistenceChecker;
use ApiKit\Validator\Constraint\EntityExistenceCheckerInterface;
use ApiKit\Validator\Constraint\EntityExistsValidator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes Doctrine-related definitions if doctrine.orm.entity_manager
 * is not present in the container.
 *
 * This guards against a specific edge case: doctrine/orm is installed
 * (interface_exists returns true, which triggers registration in ApiKitExtension),
 * but DoctrineBundle is not configured — so no EntityManagerInterface service
 * exists in the container and autowiring would fail at compile time.
 *
 * Real-world projects with Doctrine properly set up are not affected.
 */
final class RegisterDoctrineCheckerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('doctrine.orm.entity_manager')) {
            return;
        }

        foreach ([
            DoctrineEntityExistenceChecker::class,
            EntityExistsValidator::class,
        ] as $id) {
            if ($container->hasDefinition($id)) {
                $container->removeDefinition($id);
            }
        }

        if ($container->hasAlias(EntityExistenceCheckerInterface::class)) {
            $container->removeAlias(EntityExistenceCheckerInterface::class);
        }
    }
}
