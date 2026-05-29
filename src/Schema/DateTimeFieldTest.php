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

namespace League\Csv\Schema;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateTimeField::class)]
final class DateTimeFieldTest extends TestCase
{
    private DateTimeField $field;

    protected function setUp(): void
    {
        $this->field = new DateTimeField('Y-m-d');
    }

    public function testParseUsesNativeConstructorWhenFormatIsEmpty(): void
    {
        $result = $this->field->parse('2024-01-01');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
    }

    public function testParseUsesCreateFromFormatWhenFormatIsProvided(): void
    {
        $field = new DateTimeField('d-m-Y');
        $result = $field->parse('01-01-2024');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
    }

    public function testItAcceptsDateTimeInterfaceAndNormalizesToImmutable(): void
    {
        $input = new DateTime('2024-01-01');

        $result = $this->field->parse($input);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
    }

    public function testItReturnsNullForInvalidValues(): void
    {
        self::assertNull($this->field->parse(''));
        self::assertNull($this->field->parse('   '));
        self::assertNull($this->field->parse('invalid-date'));
        self::assertNull($this->field->parse([]));
        self::assertNull($this->field->parse(123));
    }

    public function test_it_can_return_another_implementing_datetime_interface(): void
    {
        $field = new DateTimeField('Y-m-d', outputClass: MyDate::class);
        $result = $field->parse('2024-01-01');

        self::assertInstanceOf(MyDate::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
        self::assertSame(MyDate::class, $field->metadata()->get('class'));
        self::assertSame('Y-m-d', $field->metadata()->get('format'));
        self::assertSame('UTC', $field->metadata()->get('timezone'));
        self::assertSame('datetime(format=Y-m-d,timezone=UTC)', $field->name());
    }

    public function test_it_uses_a_simpler_representation_for_timestamp(): void
    {
        self::assertSame('datetime(format=timestamp,timezone=UTC)', DateTimeField::timestamp()->name());
    }
}

interface MyDateInterface extends DateTimeInterface
{
}

class MyDate extends DateTimeImmutable implements MyDateInterface
{
}
