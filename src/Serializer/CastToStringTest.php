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

final class CastToStringTest extends TestCase
{
    public function testItFailsWithNonSupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToString(new ReflectionProperty(StringClass::class, 'int'));
    }

    #[DataProvider('providesValidInputValue')]
    public function testItCanConvertStringToBool(
        ReflectionProperty $reflectionProperty,
        ?string $default,
        ?string $input,
        ?string $expected
    ): void {
        self::assertSame($expected, (new CastToString($reflectionProperty, $default))->toVariable($input));
    }

    public static function providesValidInputValue(): iterable
    {
        yield 'with a string/nullable type' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'nullableString'),
            'default' => null,
            'input' => 'true',
            'expected' => 'true',
        ];

        yield 'with a string type' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'string'),
            'default' => null,
            'input' => 'yes',
            'expected' => 'yes',
        ];

        yield 'with a nullable string type and the null value' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'nullableString'),
            'default' => null,
            'input' => null,
            'expected' => null,
        ];

        yield 'with a nullable string type and a non null default value' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'nullableString'),
            'default' => 'foo',
            'input' => null,
            'expected' => 'foo',
        ];

        yield 'with union type' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'unionType'),
            'default' => 'foo',
            'input' => 'tata',
            'expected' => 'tata',
        ];

        yield 'with nullable union type' => [
            'reflectionProperty' => new ReflectionProperty(StringClass::class, 'unionType'),
            'default' => 'foo',
            'input' => null,
            'expected' => 'foo',
        ];
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $reflectionProperty = new ReflectionProperty(StringClass::class, $propertyName);

        new CastToString($reflectionProperty);
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

class StringClass
{
    public float $float;
    public ?float $nullableFloat;
    public int $int;
    public ?int $nullableInt;
    public string $string;
    public ?string $nullableString;
    public ?bool $nullableBool;
    public bool $boolean;
    public mixed $mixed;
    public ?iterable $nullableIterable;
    public array $array;
    public DateTimeInterface|string|null $unionType;
    public DateTimeInterface|int $invalidUnionType;
    public Countable&Traversable $intersectionType;
}
