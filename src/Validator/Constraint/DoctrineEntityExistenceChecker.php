<?php

declare(strict_types=1);

namespace ApiKit\Validator\Constraint;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of EntityExistenceCheckerInterface.
 * Auto-registered by ApiKitExtension when doctrine/orm is installed.
 */
final readonly class DoctrineEntityExistenceChecker implements EntityExistenceCheckerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function exists(string $entityClass, mixed $value, string $field = 'id'): bool
    {
        return null !== $this->entityManager
            ->getRepository($entityClass)
            ->findOneBy([$field => $value]);
    }
}
