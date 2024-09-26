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

use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CastToFloatTest extends TestCase
{
    public function testItFailsToInstantiateWithAnUnSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToFloat(new ReflectionProperty((new class () {
            public string $string;
        })::class, 'string'));
    }

    #[DataProvider('providesValidStringForInt')]
    public function testItCanConvertToArraygWithoutArguments(
        ReflectionProperty $prototype,
        string|int|float|null $input,
        ?float $default,
        ?float $expected
    ): void {
        $cast = new CastToFloat($prototype);
        $cast->setOptions($default);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidStringForInt(): iterable
    {
        $class = new class () {
            public ?float $nullableFloat;
            public DateTimeInterface|float|null $unionType;
        };

        yield 'positive integer' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => '1',
            'default' => null,
            'expected' => 1.0,
        ];

        yield 'zero' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => '0',
            'default' => null,
            'expected' => 0.0,
        ];

        yield 'negative integer' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => '-10',
            'default' => null,
            'expected' => -10.0,
        ];

        yield 'integer type' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => -10,
            'default' => null,
            'expected' => -10.0,
        ];

        yield 'float type' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => -10.0,
            'default' => null,
            'expected' => -10.0,
        ];

        yield 'null value' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => null,
            'default' => null,
            'expected' => null,
        ];

        yield 'null value with default value' => [
            'prototype' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => null,
            'default' => 10,
            'expected' => 10.0,
        ];

        yield 'with union type' => [
            'prototype' => new ReflectionProperty($class::class, 'unionType'),
            'input' => '23',
            'default' => 42.0,
            'expected' => 23.0,
        ];

        yield 'with nullable union type' => [
            'prototype' => new ReflectionProperty($class::class, 'unionType'),
            'input' => null,
            'default' => 42.0,
            'expected' => 42.0,
        ];
    }

    public function testItFailsToConvertNonIntegerString(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToFloat(new ReflectionProperty((new class () {
            public ?float $nullableFloat;
        })::class, 'nullableFloat')))->toVariable('00foobar');
    }
}
