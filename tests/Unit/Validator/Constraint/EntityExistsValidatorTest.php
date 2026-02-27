<?php

declare(strict_types=1);

namespace ApiKit\Tests\Unit\Validator\Constraint;

use ApiKit\Validator\Constraint\EntityExistenceCheckerInterface;
use ApiKit\Validator\Constraint\EntityExists;
use ApiKit\Validator\Constraint\EntityExistsValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @covers \ApiKit\Validator\Constraint\EntityExistsValidator
 */
final class EntityExistsValidatorTest extends TestCase
{
    private MockObject&EntityExistenceCheckerInterface $checker;
    private EntityExistsValidator $validator;
    private MockObject&ExecutionContextInterface $context;

    protected function setUp(): void
    {
        $this->checker = $this->createMock(EntityExistenceCheckerInterface::class);
        $this->validator = new EntityExistsValidator($this->checker);
        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator->initialize($this->context);
    }

    // -------------------------------------------------------------------------
    // Success cases — no violations
    // -------------------------------------------------------------------------

    public function testNoViolationWhenEntityFound(): void
    {
        $this->checker->method('exists')->willReturn(true);

        $this->context->expects(self::never())->method('buildViolation');

        $this->validator->validate('some-id', new EntityExists(\stdClass::class));
    }

    public function testSkipsNullValue(): void
    {
        $this->checker->expects(self::never())->method('exists');
        $this->context->expects(self::never())->method('buildViolation');

        $this->validator->validate(null, new EntityExists(\stdClass::class));
    }

    public function testSkipsEmptyStringValue(): void
    {
        $this->checker->expects(self::never())->method('exists');
        $this->context->expects(self::never())->method('buildViolation');

        $this->validator->validate('', new EntityExists(\stdClass::class));
    }

    public function testAcceptsIntegerValue(): void
    {
        $this->checker->method('exists')->willReturn(true);

        $this->context->expects(self::never())->method('buildViolation');

        $this->validator->validate(42, new EntityExists(\stdClass::class));
    }

    public function testAcceptsStringableObject(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'some-id';
            }
        };

        $this->checker->method('exists')->willReturn(true);

        $this->context->expects(self::never())->method('buildViolation');

        $this->validator->validate($stringable, new EntityExists(\stdClass::class));
    }

    // -------------------------------------------------------------------------
    // Violations — entity not found
    // -------------------------------------------------------------------------

    public function testAddsViolationWhenEntityNotFound(): void
    {
        $this->checker->method('exists')->willReturn(false);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('setParameter')->willReturnSelf();
        $violationBuilder->expects(self::once())->method('addViolation');

        $this->context->expects(self::once())
            ->method('buildViolation')
            ->willReturn($violationBuilder);

        $this->validator->validate('non-existent-id', new EntityExists(\stdClass::class));
    }

    public function testViolationMessageContainsEntityShortName(): void
    {
        $this->checker->method('exists')->willReturn(false);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('addViolation')->willReturnSelf();

        $violationBuilder->expects(self::exactly(3))
            ->method('setParameter')
            ->willReturnSelf();

        $this->context->method('buildViolation')->willReturn($violationBuilder);

        $this->validator->validate('123', new EntityExists(\stdClass::class, 'id'));
    }

    public function testSearchesByCustomField(): void
    {
        $this->checker->expects(self::once())
            ->method('exists')
            ->with(\stdClass::class, 'my-post', 'slug')
            ->willReturn(false);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->method('setParameter')->willReturnSelf();
        $violationBuilder->method('addViolation')->willReturnSelf();
        $this->context->method('buildViolation')->willReturn($violationBuilder);

        $this->validator->validate('my-post', new EntityExists(\stdClass::class, field: 'slug'));
    }

    // -------------------------------------------------------------------------
    // Type errors
    // -------------------------------------------------------------------------

    public function testThrowsUnexpectedTypeExceptionForWrongConstraint(): void
    {
        $wrongConstraint = $this->createMock(Constraint::class);

        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('value', $wrongConstraint);
    }

    public function testThrowsUnexpectedValueExceptionForArrayValue(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(['not', 'a', 'scalar'], new EntityExists(\stdClass::class));
    }

    public function testThrowsUnexpectedValueExceptionForObjectValue(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(new \stdClass(), new EntityExists(\stdClass::class));
    }
}
