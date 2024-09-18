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
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'colour'));
        $orange = $cast->toVariable('orange');

        self::assertInstanceOf(Colour::class, $orange);
        self::assertSame('Orange', $orange->name);
        self::assertSame('orange', $orange->value);
    }

    public function testItCanConvertAIntegerBackedEnum(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'dayOfTheWeek'));
        $monday = $cast->toVariable('1');

        self::assertInstanceOf(DayOfTheWeek::class, $monday);
        self::assertSame('Monday', $monday->name);
        self::assertSame(1, $monday->value);
    }

    public function testItCanConvertAUnitEnum(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'currency'));
        $naira = $cast->toVariable('Naira');

        self::assertInstanceOf(Currency::class, $naira);
        self::assertSame('Naira', $naira->name);
    }

    public function testItReturnsNullWhenTheVariableIsNullable(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'nullableCurrency'));

        self::assertNull($cast->toVariable(null));
    }

    public function testItReturnsTheDefaultValueWhenTheVariableIsNullable(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'nullableCurrency'));
        $cast->setOptions('Naira');

        self::assertSame(Currency::Naira, $cast->toVariable(null));
    }

    public function testThrowsOnNullIfTheVariableIsNotNullable(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToEnum(new ReflectionProperty(EnumClass::class, 'currency')))->toVariable(null);
    }

    public function testThrowsIfTheValueIsNotRecognizedByTheEnum(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToEnum(new ReflectionProperty(EnumClass::class, 'colour')))->toVariable('green');
    }

    public function testItReturnsTheDefaultValueWithUnionType(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'unionType'));
        $cast->setOptions('orange');

        self::assertSame(Colour::Violet, $cast->toVariable('violet'));
    }

    public function testItCanConvertABackedEnum(): void
    {
        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'colour'));
        $orange = $cast->toVariable(Colour::Orange);

        self::assertInstanceOf(Colour::class, $orange);
        self::assertSame('Orange', $orange->name);
        self::assertSame('orange', $orange->value);
    }

    public function testItWillThrowIfNotTheExpectedEnum(): void
    {
        $this->expectException(TypeCastingFailed::class);

        $cast = new CastToEnum(new ReflectionProperty(EnumClass::class, 'colour'));
        $cast->toVariable(DayOfTheWeek::Monday);
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $reflectionProperty = new ReflectionProperty(EnumClass::class, $propertyName);

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

class EnumClass
{
    public DayOfTheWeek $dayOfTheWeek;
    public Currency $currency;
    public ?Currency $nullableCurrency;
    public Colour $colour;
    public ?bool $nullableBool;
    public DateTimeInterface|Colour|null $unionType;
    public DateTimeInterface|int $invalidUnionType;
    public Countable&Traversable $intersectionType;
}
