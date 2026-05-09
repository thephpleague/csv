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
}
