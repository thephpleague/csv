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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class CastToDateTest extends TestCase
{
    public function testItCanConvertADateWithoutArguments(): void
    {
        $cast = new CastToDate();
        $date = $cast->toVariable('2023-10-30', DateTime::class);

        self::assertInstanceOf(DateTime::class, $date);
        self::assertSame('30-10-2023', $date->format('d-m-Y'));
    }

    public function testItCanConvertADateWithASpecificFormat(): void
    {
        $cast = new CastToDate('!Y-m-d', 'Africa/Kinshasa');
        $date = $cast->toVariable('2023-10-30', DateTimeInterface::class);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('30-10-2023 00:00:00', $date->format('d-m-Y H:i:s'));
        self::assertEquals(new DateTimeZone('Africa/Kinshasa'), $date->getTimezone());
    }

    public function testItCanConvertAnObjectImplementingTheDateTimeInterface(): void
    {
        $cast = new CastToDate();
        $date = $cast->toVariable('2023-10-30', MyDate::class);

        self::assertInstanceOf(MyDate::class, $date);
        self::assertSame('30-10-2023', $date->format('d-m-Y'));
    }

    public function testItCShouldThrowIfNoConversionIsPossible(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToDate())->toVariable('foobar', DateTimeInterface::class);
    }

    public function testItReturnsNullWhenTheVariableIsNullable(): void
    {
        $cast = new CastToDate();

        self::assertNull($cast->toVariable(null, '?'.DateTime::class));
    }
}

class MyDate extends DateTimeImmutable
{
}
