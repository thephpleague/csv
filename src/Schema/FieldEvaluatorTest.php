<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(FieldEvaluator::class)]
final class FieldEvaluatorTest extends TestCase
{
    // --------------------------------------------------------
    // confidence threshold
    // --------------------------------------------------------

    public function testItAcceptsValidConfidenceThreshold(): void
    {
        $field = new DummyField(0.5);

        self::assertSame(0.5, $field->confidenceThreshold());
    }

    public function testItThrowsForInvalidConfidenceThreshold(): void
    {
        $this->expectException(ValueError::class);

        new DummyField(1.5);
    }

    // --------------------------------------------------------
    // evaluate()
    // --------------------------------------------------------

    public function testEvaluateReturnsZeroForNull(): void
    {
        $field = new DummyField();

        self::assertSame(0, $field->evaluate(null));
    }

    public function testEvaluateReturnsZeroForEmptyString(): void
    {
        $field = new DummyField();

        self::assertSame(0, $field->evaluate(''));
        self::assertSame(0, $field->evaluate('   '));
    }

    public function testEvaluateReturnsOneForValidValue(): void
    {
        $field = new DummyField();

        self::assertSame(1, $field->evaluate('valid-value'));
    }

    public function testEvaluateReturnsMinusOneForInvalidValue(): void
    {
        $field = new DummyField();

        self::assertSame(-1, $field->evaluate('invAlid'));
    }
}

final class DummyField extends FieldEvaluator
{
    public function type(): FieldType
    {
        return FieldType::String;
    }

    public function name(): string
    {
        return 'dummy';
    }

    public function parse(mixed $value): ?string
    {
        return is_string($value) && str_contains($value, 'valid')
            ? $value
            : null;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }
}
