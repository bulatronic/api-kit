<?php

declare(strict_types=1);

namespace ApiKit\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validator for checking entity existence in the database.
 *
 * IMPORTANT: Requires an EntityExistenceCheckerInterface implementation.
 * The bundle auto-registers DoctrineEntityExistenceChecker when doctrine/orm is installed.
 * For custom persistence backends, register your own implementation and bind it to
 * EntityExistenceCheckerInterface in your container configuration.
 */
final class EntityExistsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EntityExistenceCheckerInterface $checker,
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

        if (!$this->checker->exists($constraint->entityClass, $value, $constraint->field)) {
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
