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

#[CoversClass(StructuredStringConstraint::class)]
#[CoversClass(StringLengthConstraint::class)]
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
            [' world ', 'world'],
            ['', null],
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

    public function test_max_length_constraint_applied(): void
    {
        $field = StringField::max(3);

        self::assertSame('string[,3]', $field->name());
        self::assertNull($field->parse(null));
        self::assertNull($field->parse('abcdef'));
        self::assertSame('a', $field->parse('a'));
        self::assertSame('ab', $field->parse('ab'));
        self::assertSame('abc', $field->parse('abc'));
    }

    public function test_fixed_length_constraint_applied(): void
    {
        $field = StringField::fixed(3);

        self::assertSame('string[3]', $field->name());
        self::assertNull($field->parse(null));
        self::assertNull($field->parse('abcdef'));
        self::assertNull($field->parse('a'));
        self::assertNull($field->parse('ab'));
        self::assertSame('abc', $field->parse('abc'));
    }

    public function test_min_length_constraint_applied(): void
    {
        $field = StringField::min(3);

        self::assertSame('string[3,]', $field->name());
        self::assertNull($field->parse(null));
        self::assertNull($field->parse('a'));
        self::assertNull($field->parse('ab'));
        self::assertSame('abc', $field->parse('abc'));
        self::assertSame('abcdef', $field->parse('abcdef'));
    }

    // --------------------------------------------------------
    // Factory constructors
    // --------------------------------------------------------

    public function testUuidFactoryCreatesValidStrategy(): void
    {
        $field = StringField::uuid();

        self::assertSame(FieldType::String, $field->type());
        self::assertSame('string(uuid)', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
    }

    public function testUlidFactoryCreatesValidStrategy(): void
    {
        $field = StringField::ulid();

        self::assertSame('string(ulid)', $field->name());
    }

    public function testHexColorFactoryCreatesValidStrategy(): void
    {
        $field = StringField::hexColor();

        self::assertSame('string(hex_color)', $field->name());
    }

    public function testJwtTokenFactoryCreatesValidStrategy(): void
    {
        $field = StringField::jwtToken();

        self::assertSame('string(jwt_token)', $field->name());
    }
}
