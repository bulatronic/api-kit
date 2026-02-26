<?php

declare(strict_types=1);

namespace ApiKit\DependencyInjection\Compiler;

use ApiKit\Validator\Constraint\EntityExistsValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers EntityExistsValidator only when Doctrine ORM is available.
 *
 * Runs after all bundle extensions have loaded, so doctrine.orm.entity_manager
 * is guaranteed to be present if DoctrineBundle is installed.
 */
final class DoctrineValidatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            !\interface_exists(EntityManagerInterface::class)
            || !$container->hasDefinition('doctrine.orm.entity_manager')
        ) {
            return;
        }

        $definition = new Definition(EntityExistsValidator::class);
        $definition->setArgument('$entityManager', new Reference('doctrine.orm.entity_manager'));
        $definition->addTag('validator.constraint_validator');

        $container->setDefinition(EntityExistsValidator::class, $definition);
    }
}
