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

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \League\Csv\ValueConverter
 */
final class ValueConverterTest extends TestCase
{
    /**
     * @dataProvider providesInteger
     */
    public function testItCanConvertToInteger(string|null $input, string|int|null $expected): void
    {
        self::assertSame($expected, ValueConverter::create()->convertToInteger($input));
    }

    public function providesInteger(): iterable
    {
        yield 'convert to int' => [
            'input' => '42',
            'expected' => 42,
        ];

        yield 'convert null' => [
            'input' => null,
            'expected' => null,
        ];

        yield 'convert string' => [
            'input' => '42foobar',
            'expected' => '42foobar',
        ];
    }

    /**
     * @dataProvider providesFloat
     */
    public function testItCanConvertToFloat(string|null $input, string|float|null $expected): void
    {
        self::assertSame($expected, ValueConverter::create()->convertToFloat($input));
    }

    public function providesFloat(): iterable
    {
        yield 'convert to float with dot' => [
            'input' => '42.0',
            'expected' => 42.0,
        ];

        yield 'convert to float with comma' => [
            'input' => '42,0',
            'expected' => 42.0,
        ];

        yield 'convert null' => [
            'input' => null,
            'expected' => null,
        ];

        yield 'convert string' => [
            'input' => '42.0foobar',
            'expected' => '42.0foobar',
        ];
    }

    /**
     * @dataProvider providesBoolean
     */
    public function testItCanConvertToBoolean(string|null $input, string|bool|null $expected): void
    {
        self::assertSame($expected, ValueConverter::create()->convertToBoolean($input));
    }

    public function providesBoolean(): iterable
    {
        yield 'convert to boolean from yes' => [
            'input' => 'yes',
            'expected' => true,
        ];

        yield 'convert to boolean case insensitive' => [
            'input' => 'OfF',
            'expected' => false,
        ];

        yield 'convert null' => [
            'input' => null,
            'expected' => null,
        ];

        yield 'convert string' => [
            'input' => 'truthy',
            'expected' => 'truthy',
        ];
    }

    /**
     * @dataProvider providesDate
     */
    public function testItCanConvertToDate(string|null $input, string $format, string|DateTimeImmutable|null $expected): void
    {
        $result = ValueConverter::includeDate($format)->convertToDate($input);
        if ($expected instanceof DateTimeImmutable) {
            self::assertEquals($expected, $result);

            return;
        }

        self::assertSame($expected, $result);
    }

    public function providesDate(): iterable
    {
        yield 'convert to date from string' => [
            'input' => '2022-12-03',
            'format' => '!Y-m-d',
            'expected' => new DateTimeImmutable('2022-12-03'),
        ];

        yield 'convert null' => [
            'input' => null,
            'format' => 'Y-m-d',
            'expected' => null,
        ];

        yield 'convert not matched datetime' => [
            'input' => '2022-12-03',
            'format' => 'Y-m-d\Th:i:s',
            'expected' => '2022-12-03',
        ];
    }

    /**
     * @dataProvider providesConversionInput
     */
    public function testItCanConvertToExpectedType(string|null $input, string $format, string|DateTimeImmutable|bool|float|int|null $expected): void
    {
        $result = ValueConverter::includeDate($format)->convert($input);
        if ($expected instanceof DateTimeImmutable) {
            self::assertEquals($expected, $result);

            return;
        }

        self::assertSame($expected, $result);
    }

    public function providesConversionInput(): iterable
    {
        yield 'convert to date' => [
            'input' => '2022-12-03',
            'format' => '!Y-m-d',
            'expected' => new DateTimeImmutable('2022-12-03'),
        ];

        yield 'convert to boolean' => [
            'input' => 'no',
            'format' => '!Y-m-d',
            'expected' => false,
        ];

        yield 'convert to float' => [
            'input' => '42,0',
            'format' => '!Y-m-d',
            'expected' => 42.0,
        ];

        yield 'convert to integer' => [
            'input' => '42',
            'format' => '!Y-m-d',
            'expected' => 42,
        ];

        yield 'convert to null' => [
            'input' => null,
            'format' => '!Y-m-d',
            'expected' => null,
        ];

        yield 'convert to string' => [
            'input' => 'foobar',
            'format' => '!Y-m-d',
            'expected' => 'foobar',
        ];
    }

    public function testItCanConvertARecord(): void
    {
        $expected = [
            42,
            42.0,
            false,
            null,
            new DateTimeImmutable('2022-12-23'),
        ];

        $input = [
            '42',
            '42,0',
            'no',
            null,
            '23-12-2022',
        ];

        self::assertEquals($expected, ValueConverter::includeDate('!d-m-Y')($input));
    }
}
