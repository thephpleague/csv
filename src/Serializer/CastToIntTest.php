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

use Countable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Traversable;

final class CastToIntTest extends TestCase
{
    public function testItFailsToInstantiateWithAnUnSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToInt(new ReflectionProperty((new class () {
            public string $string;
        })::class, 'string'));
    }

    #[DataProvider('providesValidStringForInt')]
    public function testItCanConvertToArraygWithoutArguments(
        ReflectionProperty $property,
        string|float|int|null $input,
        ?int $default,
        ?int $expected
    ): void {
        $cast = new CastToInt($property);
        $cast->setOptions($default);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidStringForInt(): iterable
    {
        $class = new class () {
            public ?float $nullableFloat;
            public ?int $nullableInt;
            public DateTimeInterface|int|null $unionType;
        };

        yield 'positive integer' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];

        yield 'zero' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => '0',
            'default' => null,
            'expected' => 0,
        ];

        yield 'negative integer' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => '-10',
            'default' => null,
            'expected' => -10,
        ];

        yield 'null value' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => null,
            'default' => null,
            'expected' => null,
        ];

        yield 'null value with default value' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion of the null value with a nullable float' => [
            'property' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion with float' => [
            'property' => new ReflectionProperty($class::class, 'nullableFloat'),
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];

        yield 'with union type' => [
            'property' => new ReflectionProperty($class::class, 'unionType'),
            'input' => '23',
            'default' => 42,
            'expected' => 23,
        ];

        yield 'with nullable union type' => [
            'property' => new ReflectionProperty($class::class, 'unionType'),
            'input' => null,
            'default' => 42,
            'expected' => 42,
        ];

        yield 'integer type' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => -10,
            'default' => null,
            'expected' => -10,
        ];

        yield 'float type' => [
            'property' => new ReflectionProperty($class::class, 'nullableInt'),
            'input' => -10.0,
            'default' => null,
            'expected' => -10,
        ];
    }

    public function testItFailsToConvertNonIntegerString(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToInt(new ReflectionProperty((new class () {
            public ?int $nullableInt;
        })::class, 'nullableInt')))->toVariable('00foobar');
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $class = new class () {
            public ?bool $nullableBool;
            public DateTimeInterface|string $invalidUnionType;
            public Countable&Traversable $intersectionType;
        };

        new CastToInt(new ReflectionProperty($class::class, $propertyName));
    }

    public static function invalidPropertyName(): iterable
    {
        return [
            'named type not supported' => ['propertyName' => 'nullableBool'],
            'union type not supported' => ['propertyName' => 'invalidUnionType'],
            'intersection type not supported' => ['propertyName' => 'intersectionType'],
        ];
    }
}
