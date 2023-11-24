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

        new CastToInt(new ReflectionProperty(IntClass::class, 'string'));
    }

    #[DataProvider('providesValidStringForInt')]
    public function testItCanConvertToArraygWithoutArguments(ReflectionProperty $reflectionProperty, ?string $input, ?int $default, ?int $expected): void
    {
        $cast = new CastToInt($reflectionProperty);
        $cast->setOptions($default);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidStringForInt(): iterable
    {
        yield 'positive integer' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableInt'),
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];

        yield 'zero' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableInt'),
            'input' => '0',
            'default' => null,
            'expected' => 0,
        ];

        yield 'negative integer' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableInt'),
            'input' => '-10',
            'default' => null,
            'expected' => -10,
        ];

        yield 'null value' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableInt'),
            'input' => null,
            'default' => null,
            'expected' => null,
        ];

        yield 'null value with default value' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableInt'),
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion of the null value with a nullable float' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableFloat'),
            'input' => null,
            'default' => 10,
            'expected' => 10,
        ];

        yield 'conversion with float' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'nullableFloat'),
            'input' => '1',
            'default' => null,
            'expected' => 1,
        ];

        yield 'with union type' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'unionType'),
            'input' => '23',
            'default' => 42,
            'expected' => 23,
        ];

        yield 'with nullable union type' => [
            'reflectionProperty' => new ReflectionProperty(IntClass::class, 'unionType'),
            'input' => null,
            'default' => 42,
            'expected' => 42,
        ];
    }

    public function testItFailsToConvertNonIntegerString(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToInt(new ReflectionProperty(IntClass::class, 'nullableInt')))->toVariable('00foobar');
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        new CastToInt(new ReflectionProperty(IntClass::class, $propertyName));
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

class IntClass
{
    public float $float;
    public ?float $nullableFloat;
    public mixed $mixed;
    public int $int;
    public ?int $nullableInt;
    public ?bool $nullableBool;
    public string $string;
    public DateTimeInterface|int|null $unionType;
    public DateTimeInterface|string $invalidUnionType;
    public Countable&Traversable $intersectionType;
}
