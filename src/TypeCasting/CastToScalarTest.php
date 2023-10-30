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

namespace League\Csv\TypeCasting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CastToScalarTest extends TestCase
{
    #[DataProvider('providesValidScalarValues')]
    public function testItCanConvertWithValidValue(?string $value, string $type, int|float|string|bool|null $expected): void
    {
        self::assertSame($expected, (new CastToScalar())->toVariable($value, $type));
    }

    public static function providesValidScalarValues(): iterable
    {
        yield 'it can convert an integer' => [
            'value' => '-1',
            'type' => 'int',
            'expected' => -1,
        ];

        yield 'it can convert a float' => [
            'value' => '-1.5',
            'type' => 'float',
            'expected' => -1.5,
        ];

        yield 'it can convert a boolean true' => [
            'value' => 'true',
            'type' => 'bool',
            'expected' => true,
        ];

        yield 'it can convert a string' => [
            'value' => '1',
            'type' => 'string',
            'expected' => '1',
        ];

        yield 'it can convert a boolean false' => [
            'value' => 'f',
            'type' => 'bool',
            'expected' => false,
        ];

        yield 'it can convert null to null' => [
            'value' => null,
            'type' => 'null',
            'expected' => null,
        ];

        yield 'it can accept nullable int' => [
            'value' => null,
            'type' => '?int',
            'expected' => null,
        ];

        yield 'it can accept nullable float' => [
            'value' => null,
            'type' => '?float',
            'expected' => null,
        ];

        yield 'it can accept nullable string' => [
            'value' => null,
            'type' => '?string',
            'expected' => null,
        ];
    }

    public function testItThrowsIfTheConversionFails(): void
    {
        $this->expectException(RuntimeException::class);

        (new CastToScalar())->toVariable(null, 'int');
    }
}
