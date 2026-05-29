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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

#[CoversClass(NumericField::class)]
final class NumericFieldTest extends TestCase
{
    private NumericField $field;

    protected function setUp(): void
    {
        $this->field = new NumericField();
    }

    // --------------------------------------------------------
    // VALID VALUES → float
    // --------------------------------------------------------

    public static function provideValidNumericValues(): array
    {
        return [
            'positive int' => [10, 10],
            'negative int' => [-5, -5],
            'zero' => [0, 0],
            'positive float' => [10.5, 10.5],
            'negative float' => [-3.14, -3.14],
            'string positive int' => ['10', 10],
            'string positive float' => ['10.5', 10.5],
            'string negative int' => ['-2', -2],
            'string positive int with extra spaces' => [' 12 ', 12],
            'string positive float with extra spaces' => [' 3.14 ', 3.14],
            'string positive power float with extra spaces' => [' 3e14 ', 3e14],
        ];
    }

    #[DataProvider('provideValidNumericValues')]
    public function testParseValidValues(mixed $input, int|float $expected): void
    {
        self::assertSame($expected, $this->field->parse($input));
    }

    // --------------------------------------------------------
    // INVALID VALUES → null
    // --------------------------------------------------------

    public static function provideInvalidNumericValues(): array
    {
        return [
            [''],
            ['   '],
            ['abc'],
            ['12abc'],
            ['abc12'],
            [true],
            [false],
            [null],
            [[]],
            [new stdClass()],
        ];
    }

    #[DataProvider('provideInvalidNumericValues')]
    public function testParseInvalidValues(mixed $input): void
    {
        self::assertNull($this->field->parse($input));
    }

    public function test_metadata_contains_expected_structure(): void
    {
        self::assertFalse($this->field->metadata()->isEmpty());
    }

    // --------------------------------------------------------
    // Factory constructors
    // --------------------------------------------------------

    public function testMinFactory(): void
    {
        $field = NumericField::min(4);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[4,]', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
        self::assertSame(5, $field->parse(5));
        self::assertNull($field->parse(-4.1));
        self::assertNull($field->parse('0'));
    }

    public function testMaxFactory(): void
    {
        $field = NumericField::max(4);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[,4]', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
        self::assertNull($field->parse(5));
        self::assertSame(-4.1, $field->parse(-4.1));
        self::assertSame(0, $field->parse('0'));
    }

    public function testFixedFactory(): void
    {
        $field = NumericField::fixed(4);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[4]', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
        self::assertNull($field->parse(5));
        self::assertNull($field->parse(-4.1));
        self::assertSame(4, $field->parse('4'));
    }

    public function testBetweenFactory(): void
    {
        $field = NumericField::between(-4, 4);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[-4,4]', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
        self::assertNull($field->parse(5));
        self::assertNull($field->parse(-4.1));
        self::assertSame(0, $field->parse('0'));
    }

    public function testPositiveFactory(): void
    {
        $field = NumericField::positive(.5);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[0,]', $field->name());
        self::assertSame(0.5, $field->confidenceThreshold());
    }

    public function testNegativeFactory(): void
    {
        $field = NumericField::negative(1);

        self::assertSame(FieldType::Numeric, $field->type());
        self::assertSame('numeric[,0]', $field->name());
        self::assertSame(1.0, $field->confidenceThreshold());
    }

    public function testItFailsToInstantiateBetweenFactoryWithInvalidValues(): void
    {
        $this->expectException(ValueError::class);

        NumericField::between(4, -4);
    }
}
