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

use PHPUnit\Framework\TestCase;

final class CastToEnumTest extends TestCase
{
    public function testItCanConvertAStringBackedEnum(): void
    {
        $cast = new CastToEnum();
        $orange = $cast->toVariable('orange', Colour::class);

        self::assertInstanceOf(Colour::class, $orange);
        self::assertSame('Orange', $orange->name);
        self::assertSame('orange', $orange->value);
    }

    public function testItCanConvertAIntegerBackedEnum(): void
    {
        $cast = new CastToEnum();
        $monday = $cast->toVariable('1', DayOfTheWeek::class);

        self::assertInstanceOf(DayOfTheWeek::class, $monday);
        self::assertSame('Monday', $monday->name);
        self::assertSame(1, $monday->value);
    }

    public function testItCanConvertAUnitEnum(): void
    {
        $cast = new CastToEnum();
        $naira = $cast->toVariable('Naira', Currency::class);

        self::assertInstanceOf(Currency::class, $naira);
        self::assertSame('Naira', $naira->name);
    }

    public function testItReturnsNullWhenTheVariableIsNullable(): void
    {
        $cast = new CastToEnum();

        self::assertNull($cast->toVariable(null, '?'.Currency::class));
    }

    public function testThrowsOnNullIfTheVariableIsNotNullable(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToEnum())->toVariable(null, Currency::class);
    }

    public function testThrowsIfTheValueIsNotRecognizedByTheEnum(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToEnum())->toVariable('green', Colour::class);
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
