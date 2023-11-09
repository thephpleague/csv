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

namespace League\Csv\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CastToFloatTest extends TestCase
{
    public function testItFailsToInstantiateWithAnUnSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToFloat('string');
    }

    #[DataProvider('providesValidStringForInt')]
    public function testItCanConvertToArraygWithoutArguments(string $prototype, ?string $input, ?float $default, ?float $expected): void
    {
        self::assertSame($expected, (new CastToFloat(propertyType: $prototype, default:$default))->toVariable($input));
    }

    public static function providesValidStringForInt(): iterable
    {
        yield 'positive integer' => [
            'prototype' => '?float',
            'input' => '1',
            'default' => null,
            'expected' => 1.0,
        ];

        yield 'zero' => [
            'prototype' => '?float',
            'input' => '0',
            'default' => null,
            'expected' => 0.0,
        ];

        yield 'negative integer' => [
            'prototype' => '?float',
            'input' => '-10',
            'default' => null,
            'expected' => -10.0,
        ];

        yield 'null value' => [
            'prototype' => '?float',
            'input' => null,
            'default' => null,
            'expected' => null,
        ];

        yield 'null value with default value' => [
            'prototype' => '?float',
            'input' => null,
            'default' => 10,
            'expected' => 10.0,
        ];
    }

    public function testItFailsToConvertNonIntegerString(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToFloat(propertyType: '?float'))->toVariable('00foobar');
    }
}
