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

final class CastToEnumTest extends TestCase
{
    public function testItCanConvertAStringBackedEnum(): void
    {
        $class = new class () {
            public Colour $colour;
        };

        $cast = new CastToEnum(new ReflectionProperty($class::class, 'colour'));
        $orange = $cast->toVariable('orange');

        self::assertInstanceOf(Colour::class, $orange);
        self::assertSame('Orange', $orange->name);
        self::assertSame('orange', $orange->value);
    }

    public function testItCanConvertAIntegerBackedEnum(): void
    {
        $class = new class () {
            public DayOfTheWeek $dayOfTheWeek;
        };

        $cast = new CastToEnum(new ReflectionProperty($class::class, 'dayOfTheWeek'));
        $monday = $cast->toVariable('1');

        self::assertInstanceOf(DayOfTheWeek::class, $monday);
        self::assertSame('Monday', $monday->name);
        self::assertSame(1, $monday->value);
    }

    public function testItCanConvertAUnitEnum(): void
    {
        $class = new class () {
            public Currency $currency;
        };

        $cast = new CastToEnum(new ReflectionProperty($class::class, 'currency'));
        $naira = $cast->toVariable('Naira');

        self::assertInstanceOf(Currency::class, $naira);
        self::assertSame('Naira', $naira->name);
    }

    public function testItReturnsNullWhenTheVariableIsNullable(): void
    {
        $class = new class () {
            public ?Currency $nullableCurrency;
        };

        $cast = new CastToEnum(new ReflectionProperty($class::class, 'nullableCurrency'));

        self::assertNull($cast->toVariable(null));
    }

    public function testItReturnsTheDefaultValueWhenTheVariableIsNullable(): void
    {
        $class = new class () {
            public ?Currency $nullableCurrency;
        };

        $cast = new CastToEnum(new ReflectionProperty($class::class, 'nullableCurrency'));
        $cast->setOptions('Naira');

        self::assertSame(Currency::Naira, $cast->toVariable(null));
    }

    public function testThrowsOnNullIfTheVariableIsNotNullable(): void
    {
        $this->expectException(TypeCastingFailed::class);

        $class = new class () {
            public Currency $currency;
        };

        (new CastToEnum(new ReflectionProperty($class::class, 'currency')))->toVariable(null);
    }

    public function testThrowsIfTheValueIsNotRecognizedByTheEnum(): void
    {
        $this->expectException(TypeCastingFailed::class);
        $class = new class () {
            public Colour $colour;
        };
        (new CastToEnum(new ReflectionProperty($class::class, 'colour')))->toVariable('green');
    }

    public function testItReturnsTheDefaultValueWithUnionType(): void
    {
        $class = new class () {
            public DateTimeInterface|Colour|null $unionType;
        };
        $cast = new CastToEnum(new ReflectionProperty($class::class, 'unionType'));
        $cast->setOptions('orange');

        self::assertSame(Colour::Violet, $cast->toVariable('violet'));
    }

    public function testItCanConvertABackedEnum(): void
    {
        $class = new class () {
            public Colour $colour;
        };
        $cast = new CastToEnum(new ReflectionProperty($class::class, 'colour'));
        $orange = $cast->toVariable(Colour::Orange);

        self::assertInstanceOf(Colour::class, $orange);
        self::assertSame('Orange', $orange->name);
        self::assertSame('orange', $orange->value);
    }

    public function testItWillThrowIfNotTheExpectedEnum(): void
    {
        $this->expectException(TypeCastingFailed::class);
        $class = new class () {
            public Colour $colour;
        };
        $cast = new CastToEnum(new ReflectionProperty($class::class, 'colour'));
        $cast->toVariable(DayOfTheWeek::Monday);
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);
        $class = new class () {
            public ?bool $nullableBool;
            public DateTimeInterface|int $invalidUnionType;
            public Countable&Traversable $intersectionType;
        };
        $reflectionProperty = new ReflectionProperty($class::class, $propertyName);

        new CastToEnum($reflectionProperty);
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

enum Colour: string
{
    case Orange = 'orange';
    case Violet = 'violet';
}

enum DayOfTheWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
}

enum Currency
{
    case Dollar;
    case Euro;
    case Naira;
}
