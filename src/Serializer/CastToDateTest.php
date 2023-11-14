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
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CastToDateTest extends TestCase
{
    public function testItCanConvertADateWithoutArguments(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'dateTime'));
        $date = $cast->toVariable('2023-10-30');

        self::assertInstanceOf(DateTime::class, $date);
        self::assertSame('30-10-2023', $date->format('d-m-Y'));
    }

    public function testItCanConvertADateWithASpecificFormat(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'dateTimeInterface'), null, '!Y-m-d', 'Africa/Kinshasa');
        $date = $cast->toVariable('2023-10-30');

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('30-10-2023 00:00:00', $date->format('d-m-Y H:i:s'));
        self::assertEquals(new DateTimeZone('Africa/Kinshasa'), $date->getTimezone());
    }

    public function testItCanConvertAnObjectImplementingTheDateTimeInterface(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'myDate'));
        $date = $cast->toVariable('2023-10-30');

        self::assertInstanceOf(MyDate::class, $date);
        self::assertSame('30-10-2023', $date->format('d-m-Y'));
    }

    public function testItCShouldThrowIfNoConversionIsPossible(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToDate(new ReflectionProperty(DateClass::class, 'dateTimeInterface')))->toVariable('DateClass');
    }



    public function testItCShouldThrowIfTheOptionsAreInvalid(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToDate(
            new ReflectionProperty(DateClass::class, 'dateTimeInterface'),
            '2023-11-11',
            'Y-m-d',
            'Europe\Blan'
        );
    }


    public function testItReturnsNullWhenTheVariableIsNullable(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'nullableDateTime'));

        self::assertNull($cast->toVariable(null));
    }

    public function testItCanConvertADateWithADefaultValue(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'nullableDateTimeInterface'), '2023-01-01', '!Y-m-d', 'Africa/Kinshasa');
        $date = $cast->toVariable(null);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('01-01-2023 00:00:00', $date->format('d-m-Y H:i:s'));
        self::assertEquals(new DateTimeZone('Africa/Kinshasa'), $date->getTimezone());
    }

    public function testItReturnsTheValueWithUnionType(): void
    {
        $cast = new CastToDate(new ReflectionProperty(DateClass::class, 'unionType'), '2023-01-01');

        self::assertEquals(new DateTimeImmutable('2023-01-01'), $cast->toVariable(null));
    }

    #[DataProvider('invalidPropertyName')]
    public function testItWillThrowIfNotTypeAreSupported(string $propertyName): void
    {
        $this->expectException(MappingFailed::class);

        $reflectionProperty = new ReflectionProperty(DateClass::class, $propertyName);

        new CastToDate($reflectionProperty);
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

class MyDate extends DateTimeImmutable
{
}

class DateClass
{
    public DateTimeImmutable $dateTimeImmutable;
    public DateTime $dateTime;
    public DateTimeInterface $dateTimeInterface;
    public MyDate $myDate;
    public ?DateTimeImmutable $nullableDateTimeImmutable;
    public ?DateTime $nullableDateTime;
    public ?DateTimeInterface $nullableDateTimeInterface;
    public ?MyDate $nullableMyDate;
    public mixed $mixed;
    public ?bool $nullableBool;
    public DateTimeInterface|string|null $unionType;
    public float|int $invalidUnionType;
    public Countable&DateTimeInterface $intersectionType;
}
