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

final class CastToInTest extends TestCase
{
    public function testItFailsToInstantiateWithAnUnSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToInt('string');
    }

    #[DataProvider('providesValidStringForInt')]
    public function testItCanConvertToArraygWithoutArguments(string $prototype, ?string $input, ?int $default, ?int $expected): void
    {
        self::assertSame($expected, (new CastToInt(propertyType: $prototype, default:$default))->toVariable($input));
    }

    public static function providesValidStringForInt(): iterable
    {
        yield 'positive integer' => [
            'prototype' => '?int',
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];

        yield 'zero' => [
            'prototype' => '?int',
            'input' => '0',
            'default' => null,
            'expected' => 0,
        ];

        yield 'negative integer' => [
            'prototype' => '?int',
            'input' => '-10',
            'default' => null,
            'expected' => -10,
        ];

        yield 'null value' => [
            'prototype' => '?int',
            'input' => null,
            'default' => null,
            'expected' => null,
        ];

        yield 'null value with default value' => [
            'prototype' => '?int',
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion of the null value with a nullable float' => [
            'prototype' => '?float',
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion with float' => [
            'prototype' => '?float',
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];
    }

    public function testItFailsToConvertNonIntegerString(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToInt(propertyType: '?int'))->toVariable('00foobar');
    }
}
