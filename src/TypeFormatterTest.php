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

namespace League\Csv;

use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

use function fclose;
use function fopen;

use const INF;
use const NAN;
use const PHP_FLOAT_MAX;
use const PHP_FLOAT_MIN;

enum StringBackedValue: string
{
    case Value = 'example';
}

enum IntBackedValue: int
{
    case Value = 42;
}

enum UnitValue
{
    case Value;
}

final class TypeFormatterTest extends TestCase
{
    public function test_default_boolean_values(): void
    {
        $formatter = new TypeFormatter();

        self::assertSame('1', $formatter->encode(true));
        self::assertSame('0', $formatter->encode(false));
    }

    public function test_custom_boolean_values(): void
    {
        $formatter = new TypeFormatter('yes', 'no');

        self::assertSame('yes', $formatter->encode(true));
        self::assertSame('no', $formatter->encode(false));
    }

    #[DataProvider('provide_scalar_values')]
    public function test_encode_scalar_values(mixed $value, string $expected): void
    {
        self::assertSame($expected, (new TypeFormatter())->encode($value));
    }

    public static function provide_scalar_values(): iterable
    {
        yield 'null' => [null, ''];
        yield 'empty string' => ['', ''];
        yield 'string' => ['hello', 'hello'];
        yield 'integer zero' => [0, '0'];
        yield 'positive integer' => [42, '42'];
        yield 'negative integer' => [-42, '-42'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, '0'];
    }

    #[DataProvider('provide_finite_float_values')]
    public function test_encode_finite_float_values(float $value, string $expected): void
    {
        self::assertSame($expected, (new TypeFormatter())->encode($value));
    }

    public static function provide_finite_float_values(): iterable
    {
        yield 'zero' => [0.0, '0'];
        yield 'negative zero' => [-0.0, '-0'];
        yield 'integer-like float' => [1.0, '1'];
        yield 'positive float' => [1.5, '1.5'];
        yield 'negative float' => [-1.5, '-1.5'];
        yield 'small float' => [0.0000001, '1.0e-7'];
        yield 'large float' => [1.0e20, '1.0e+20'];
        yield 'large float formatting' => [1.0E20, '1.0e+20'];
        yield 'precision' => [0.123456789012345, '0.123456789012345'];
        yield 'maximum finite float' => [PHP_FLOAT_MAX, '1.7976931348623157e+308'];
        yield 'minimum positive float' => [PHP_FLOAT_MIN, '2.2250738585072014e-308'];
    }

    #[DataProvider('provide_non_finite_float_values')]
    public function test_encode_non_finite_float_values(float $value): void
    {
        self::assertSame(
            (string) $value,
            (new TypeFormatter())->encode($value)
        );
    }

    public static function provide_non_finite_float_values(): iterable
    {
        yield 'NAN' => [NAN];
        yield 'INF' => [INF];
        yield '-INF' => [-INF];
    }

    #[DataProvider('provide_backed_enum_values')]
    public function test_encode_backed_enum_values(
        BackedEnum $value,
        string $expected,
    ): void {
        self::assertSame($expected, (new TypeFormatter())->encode($value));
    }

    public static function provide_backed_enum_values(): iterable
    {
        yield 'string backed enum' => [StringBackedValue::Value, 'example'];
        yield 'integer backed enum' => [IntBackedValue::Value, '42'];
    }

    public function test_invoke_formats_a_record(): void
    {
        $formatter = new TypeFormatter('yes', 'no');

        $record = [
            'name' => 'John',
            'age' => 42,
            'active' => true,
            'verified' => false,
            'score' => 12.5,
            'status' => StringBackedValue::Value,
            'optional' => null,
        ];

        self::assertSame([
            'name' => 'John',
            'age' => '42',
            'active' => 'yes',
            'verified' => 'no',
            'score' => '12.5',
            'status' => 'example',
            'optional' => '',
        ], $formatter($record));
    }

    public function test_invoke_preserves_numeric_keys(): void
    {
        $formatter = new TypeFormatter();

        self::assertSame([
            0 => 'hello',
            2 => '42',
        ], $formatter([
            0 => 'hello',
            2 => 42,
        ]));
    }

    #[DataProvider('provide_unsupported_values')]
    public function test_encode_rejects_unsupported_values(
        mixed $value,
        string $type,
    ): void {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'Value must be of type null|bool|int|float|string|BackedEnum; '.$type.' given'
        );

        (new TypeFormatter())->encode($value);
    }

    public static function provide_unsupported_values(): iterable
    {
        yield 'unit enum' => [UnitValue::Value, UnitValue::class];
        yield 'array' => [[], 'array'];
        yield 'object' => [new stdClass(), 'stdClass'];
    }

    public function test_encode_rejects_resource(): void
    {
        $resource = fopen('php://memory', 'r');

        self::assertIsResource($resource);

        try {
            $this->expectException(TypeError::class);
            $this->expectExceptionMessage('resource');

            (new TypeFormatter())->encode($resource);
        } finally {
            fclose($resource);
        }
    }

    public function test_encode_includes_field_name_in_exception(): void
    {
        try {
            (new TypeFormatter())->encode([], 'amount');

            self::fail('Expected TypeError was not thrown.');
        } catch (TypeError $exception) {
            self::assertSame('Field “amount” value must be of type null|bool|int|float|string|BackedEnum; array given.', $exception->getMessage());
        }
    }

    public function test_encode_without_field_name_uses_generic_exception(): void
    {
        try {
            (new TypeFormatter())->encode(new stdClass());

            self::fail('Expected TypeError was not thrown.');
        } catch (TypeError $exception) {
            self::assertStringContainsString('Value must be of type', $exception->getMessage());
        }
    }

    public function test_it_can_be_used_as_a_writer_formatter(): void
    {
        $arr = ['city' => StringBackedValue::Value, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(new TypeFormatter());
        $doc->insertOne($arr);

        self::assertSame('example,7000000'."\n", $doc->toString());
    }
}
