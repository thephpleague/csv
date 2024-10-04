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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Traversable;

final class CastToArrayTest extends TestCase
{
    /**
     * @param 'csv'|'json'|'list' $shape
     * @param array<array-key, int|string> $expected
     */
    #[DataProvider('providesValidStringForArray')]
    public function testItCanConvertToArraygWithoutArguments(string $shape, string $type, string|array $input, array $expected): void
    {
        $cast = new CastToArray(new ReflectionProperty((new class () {
            public ?iterable $nullableIterable;
        })::class, 'nullableIterable'));
        $cast->setOptions(shape:$shape, type:$type);

        self::assertSame($expected, $cast->toVariable($input));
    }

    public static function providesValidStringForArray(): iterable
    {
        yield 'using the list shape' => [
            'shape' => 'list',
            'type' => 'string',
            'input' => '1,2,3,4',
            'expected' => ['1', '2', '3', '4'],
        ];

        yield 'using the list shape with the float type' => [
            'shape' => 'list',
            'type' => 'float',
            'input' => '1,2,3,4',
            'expected' => [1.0, 2.0, 3.0, 4.0],
        ];

        yield 'using the list shape with the int type' => [
            'shape' => 'list',
            'type' => 'int',
            'input' => '1,2,3,4',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the list shape with the bool type' => [
            'shape' => 'list',
            'type' => 'bool',
            'input' => '1,on,true,yes',
            'expected' => [true, true, true, true],
        ];

        yield 'using the json shape' => [
            'shape' => 'json',
            'type' => 'string',
            'input' => '[1,2,3,4]',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the json shape is not affected by the type argument' => [
            'shape' => 'json',
            'type' => 'iterable',
            'input' => '[1,2,3,4]',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the csv shape' => [
            'shape' => 'csv',
            'type' => 'string',
            'input' => '"1",2,3,"4"',
            'expected' => [['1', '2', '3', '4']],
        ];

        yield 'using the csv shape with type int' => [
            'shape' => 'csv',
            'type' => 'int',
            'input' => '"1",2,3,"4"',
            'expected' => [[1, 2, 3, 4]],
        ];

        yield 'using an array overrides every other settings' => [
            'shape' => 'csv',
            'type' => 'int',
            'input' => [1, 2, 3, 4],
            'expected' => [1, 2, 3, 4],
        ];
    }

    public function testItFailsToCastAnUnsupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToArray(new ReflectionProperty((new class () {
            public ?int $nullableInt;
        })::class, 'nullableInt'));
    }

    public function testItFailsToCastInvalidJson(): void
    {
        $this->expectException(TypeCastingFailed::class);
        $cast = new CastToArray(new ReflectionProperty((new class () {
            public ?iterable $nullableIterable;
        })::class, 'nullableIterable'));
        $cast->setOptions(shape: 'json');
        $cast->toVariable('{"json":toto}');
    }

    public function testItCastNullableJsonUsingTheDefaultValue(): void
    {
        $defaultValue = ['toto'];

        $cast = new CastToArray(new ReflectionProperty((new class () {
            public ?iterable $nullableIterable;
        })::class, 'nullableIterable'));
        $cast->setOptions(default: $defaultValue, shape: 'json');

        self::assertSame($defaultValue, $cast->toVariable(null));
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $class = new class () {
            public ?int $nullableInt;
            public array $array;
            public DateTimeInterface|array|null $unionType;
            public DateTimeInterface|string $invalidUnionType;
            public Countable&Traversable $intersectionType;
        };

        $reflectionProperty = new ReflectionProperty($class::class, $propertyName);

        new CastToArray($reflectionProperty);
    }

    public static function invalidPropertyName(): iterable
    {
        return [
            'named type not supported' => ['propertyName' => 'nullableInt'],
            'union type not supported' => ['propertyName' => 'invalidUnionType'],
            'intersection type not supported' => ['propertyName' => 'intersectionType'],
        ];
    }

    #[Test]
    public function it_can_trim_array_value_if_applicable(): void
    {
        $cast = new CastToArray(new ReflectionProperty((new class () {
            public ?iterable $nullableIterable;
        })::class, 'nullableIterable'));
        $cast->setOptions(shape: 'list', trimElementValueBeforeCasting: true);

        $string = 'john , john, foo';

        self::assertSame(['john', 'john', 'foo'], $cast->toVariable($string));

        $cast->setOptions(shape: 'list');

        self::assertSame(['john ', ' john', ' foo'], $cast->toVariable($string));
    }
}
