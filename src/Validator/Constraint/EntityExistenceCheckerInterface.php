<?php

declare(strict_types=1);

namespace ApiKit\Validator\Constraint;

/**
 * Port for checking entity existence.
 * Implement this interface to provide a custom persistence backend.
 * The bundle ships with DoctrineEntityExistenceChecker (auto-registered when doctrine/orm is installed).
 */
interface EntityExistenceCheckerInterface
{
    /**
     * @param class-string $entityClass
     */
    public function exists(string $entityClass, mixed $value, string $field = 'id'): bool;
}
