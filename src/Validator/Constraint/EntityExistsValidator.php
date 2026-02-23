<?php

declare(strict_types=1);

namespace ApiKit\Validator\Constraint;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator for checking entity existence in the database.
 *
 * IMPORTANT: Requires Doctrine ORM.
 * Install dependencies: composer require doctrine/orm doctrine/doctrine-bundle
 */
final class EntityExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EntityExists) {
            throw new UnexpectedTypeException($constraint, EntityExists::class);
        }

        // Skip null values - let NotBlank handle this
        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string|int|Stringable');
        }

        $repository = $this->entityManager->getRepository($constraint->entityClass);
        $entity = $repository->findOneBy([$constraint->field => $value]);

        if (null === $entity) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ entity }}', $this->getEntityShortName($constraint->entityClass))
                ->setParameter('{{ field }}', $constraint->field)
                ->setParameter('{{ value }}', (string) $value)
                ->addViolation();
        }
    }

    /**
     * Get short name of entity class.
     *
     * @param class-string $entityClass
     */
    private function getEntityShortName(string $entityClass): string
    {
        $parts = \explode('\\', $entityClass);

        return \end($parts);
    }
}
