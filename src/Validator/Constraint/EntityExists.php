<?php

declare(strict_types=1);

namespace ApiKit\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for checking entity existence in the database.
 *
 * IMPORTANT: Using this validator requires Doctrine ORM:
 * composer require doctrine/orm doctrine/doctrine-bundle
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @example
 * #[EntityExists(User::class)]
 * public readonly string $userId;
 *
 * @example
 * #[EntityExists(entityClass: Post::class, field: 'slug')]
 * public readonly string $postSlug;
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class EntityExists extends Constraint
{
    public string $message = 'Entity "{{ entity }}" with {{ field }} = "{{ value }}" not found.';

    /**
     * @param class-string $entityClass Entity class to check
     * @param string $field Field to search by (default 'id')
     * @param array<string, mixed> $groups Validation groups
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly string $field = 'id',
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);

        if (null !== $message) {
            $this->message = $message;
        }
    }

    public function validatedBy(): string
    {
        return EntityExistsValidator::class;
    }
}
