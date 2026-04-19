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

#[CoversClass(StringField::class)]
final class StringFieldTest extends TestCase
{
    private StringField $field;

    protected function setUp(): void
    {
        $this->field = new StringField();
    }

    // --------------------------------------------------------
    // parse()
    // --------------------------------------------------------

    public static function provideParseValues(): array
    {
        return [
            ['hello', 'hello'],
            [' world ', ' world '],
            ['', ''],
            ['123', '123'],
        ];
    }

    #[DataProvider('provideParseValues')]
    public function testParse(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, $this->field->parse($input));
    }

    public static function provideInvalidParseValues(): array
    {
        return [
            [123],
            [12.5],
            [true],
            [false],
            [null],
            [[]],
            [new stdClass()],
        ];
    }

    #[DataProvider('provideInvalidParseValues')]
    public function testParseReturnsNullForNonStrings(mixed $input): void
    {
        self::assertNull($this->field->parse($input));
    }

    // --------------------------------------------------------
    // evaluate()
    // --------------------------------------------------------

    public static function provideEvaluateValues(): array
    {
        return [
            ['hello', 1],
            ['', 1],
            ['123', 1],

            [123, 0],
            [12.5, 0],
            [true, 0],
            [false, 0],
            [null, 0],
            [[], 0],
        ];
    }

    #[DataProvider('provideEvaluateValues')]
    public function testEvaluate(mixed $input, int $expected): void
    {
        self::assertSame($expected, $this->field->evaluate($input));
    }

    // --------------------------------------------------------
    // score()
    // --------------------------------------------------------

    public function testScoreAlwaysReturnsOne(): void
    {
        self::assertSame(1.0, $this->field->score(['a', 'b', 'c']));
        self::assertSame(1.0, $this->field->score([1, 2, 3]));
        self::assertSame(1.0, $this->field->score([]));
    }

    // --------------------------------------------------------
    // type()
    // --------------------------------------------------------

    public function testTypeIsString(): void
    {
        self::assertSame(FieldType::String, $this->field->type());
    }

    // --------------------------------------------------------
    // confidenceThreshold()
    // --------------------------------------------------------

    public function testConfidenceThresholdIsZero(): void
    {
        self::assertSame(0.0, $this->field->confidenceThreshold());
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = new StringField();

        self::assertTrue($field->metadata()->isEmpty());
    }
}
