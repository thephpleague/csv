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

#[CoversClass(StructuredStringField::class)]
final class StructuredStringFieldTest extends TestCase
{
    // --------------------------------------------------------
    // Factory constructors
    // --------------------------------------------------------

    public function testUuidFactoryCreatesValidStrategy(): void
    {
        $field = StructuredStringField::uuid();

        self::assertSame(FieldType::StructuredString, $field->type());
        self::assertSame('uuid', $field->name());
        self::assertSame(0.8, $field->confidenceThreshold());
    }

    public function testUlidFactoryCreatesValidStrategy(): void
    {
        $field = StructuredStringField::ulid();

        self::assertSame('ulid', $field->name());
    }

    public function testHexColorFactoryCreatesValidStrategy(): void
    {
        $field = StructuredStringField::hexColor();

        self::assertSame('hex_color', $field->name());
    }

    public function testJwtTokenFactoryCreatesValidStrategy(): void
    {
        $field = StructuredStringField::jwtToken();

        self::assertSame('jwt_token', $field->name());
    }

    // --------------------------------------------------------
    // parse()
    // --------------------------------------------------------

    public static function provideValidValues(): array
    {
        return [
            // UUID v4 example
            ['550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440000'],

            // ULID example
            ['01ARZ3NDEKTSV4RRFFQ69G5FAV', '01ARZ3NDEKTSV4RRFFQ69G5FAV'],

            // hex color
            ['#ff0000', '#ff0000'],
            ['#00FF00', '#00FF00'],

            // JWT (simplified example format match depends on regex)
            ['header.payload.signature', 'header.payload.signature'],
        ];
    }

    #[DataProvider('provideValidValues')]
    public function testParseValidValues(string $input, string $expected): void
    {
        // NOTE: we test multiple strategies explicitly because pattern differs
        $fields = [
            StructuredStringField::uuid(),
            StructuredStringField::ulid(),
            StructuredStringField::hexColor(),
            StructuredStringField::jwtToken(),
        ];

        $matched = false;

        foreach ($fields as $field) {
            $result = $field->parse($input);

            if (null !== $result) {
                $matched = true;
                self::assertSame($expected, $result);
            }
        }

        self::assertTrue($matched, 'Input should match at least one strategy');
    }

    public static function provideInvalidValues(): array
    {
        return [
            [''],
            ['   '],
            ['invalid'],
            ['123'],
            [123],
            [null],
            [[]],
            ['not-a-uuid'],
        ];
    }

    #[DataProvider('provideInvalidValues')]
    public function testParseInvalidValues(mixed $input): void
    {
        $field = StructuredStringField::uuid();

        self::assertNull($field->parse($input));
    }

    // --------------------------------------------------------
    // trimming behavior
    // --------------------------------------------------------

    public function testParseTrimsInput(): void
    {
        $field = StructuredStringField::uuid();

        $value = ' 550e8400-e29b-41d4-a716-446655440000 ';

        self::assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $field->parse($value)
        );
    }

    // --------------------------------------------------------
    // type()
    // --------------------------------------------------------

    public function testTypeMatchesDefinition(): void
    {
        $field = StructuredStringField::hexColor();

        self::assertSame(FieldType::StructuredString, $field->type());
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = StructuredStringField::hexColor();

        self::assertSame(['pattern' => $field->definition->pattern], $field->metadata()->all());
    }
}
