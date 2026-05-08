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
use stdClass;
use ValueError;

#[CoversClass(EnumField::class)]
final class EnumFieldTest extends TestCase
{
    private EnumField $field;

    protected function setUp(): void
    {
        $this->field = new EnumField(TestEnum::class);
    }

    // --------------------------------------------------------
    // Construction
    // --------------------------------------------------------

    public function testItThrowsWhenClassIsNotAnEnum(): void
    {
        $this->expectException(ValueError::class);

        new EnumField(stdClass::class); /* @phpstan-ignore-line */
    }

    // --------------------------------------------------------
    // UnitEnum (non-backed enum)
    // --------------------------------------------------------

    public function testItParsesEnumByInstance(): void
    {
        $value = TestEnum::A;

        $result = $this->field->parse($value);

        self::assertSame($value, $result);
        self::assertSame(FieldType::Enum, $this->field->type());
        self::assertSame(TestEnum::class, $this->field->enumClass);
        self::assertSame('enum', $this->field->name());
    }

    public function testItParsesEnumByName(): void
    {
        $result = $this->field->parse('A');

        self::assertSame(TestEnum::A, $result);
    }

    public function testItTrimsStringInput(): void
    {
        $result = $this->field->parse(' A ');

        self::assertSame(TestEnum::A, $result);
    }

    public function testItReturnsNullForInvalidEnumName(): void
    {
        self::assertNull($this->field->parse('INVALID'));
    }

    // --------------------------------------------------------
    // BackedEnum (string/int)
    // --------------------------------------------------------

    public function testItParsesBackedEnumFromStringValue(): void
    {
        $field = new EnumField(TestBackedEnum::class);

        $result = $field->parse('a');

        self::assertSame(TestBackedEnum::A, $result);
    }

    public function testItParsesBackedEnumFromIntValue(): void
    {
        $field = new EnumField(TestIntBackedEnum::class);

        $result = $field->parse(1);

        self::assertSame(TestIntBackedEnum::A, $result);
    }

    public function testItParsesNumericStringForIntBackedEnum(): void
    {
        $field = new EnumField(TestIntBackedEnum::class);

        $result = $field->parse('1');

        self::assertSame(TestIntBackedEnum::A, $result);
    }

    public function testItReturnsNullForInvalidBackedValue(): void
    {
        $field = new EnumField(TestBackedEnum::class);

        self::assertNull($field->parse('invalid'));
        self::assertNull($field->parse([]));
        self::assertNull($field->parse(''));
    }

    // --------------------------------------------------------
    // Direct enum instance handling
    // --------------------------------------------------------

    public function testItRejectsEnumFromDifferentClass(): void
    {
        $result = $this->field->parse(OtherEnum::A);

        self::assertNull($result);
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = new EnumField(TestBackedEnum::class);

        $metadata = $field->metadata();

        self::assertSame(TestBackedEnum::class, $metadata->get('class'));
        self::assertSame('string', $metadata->get('backedType'));
        self::assertSame([
            ['name' => 'A', 'value' => 'a'],
            ['name' => 'B', 'value' => 'b'],
        ], $metadata->get('cases'));
    }
}

enum TestEnum
{
    case A;
    case B;
}

enum TestBackedEnum: string
{
    case A = 'a';
    case B = 'b';
}

enum TestIntBackedEnum: int
{
    case A = 1;
    case B = 2;
}

enum OtherEnum
{
    case A;
    case B;
}
