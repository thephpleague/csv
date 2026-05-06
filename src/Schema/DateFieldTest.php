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

#[CoversClass(DateField::class)]
final class DateFieldTest extends TestCase
{
    private DateField $field;

    protected function setUp(): void
    {
        $this->field = new DateField('Y-m-d');
    }

    public function testParseUsesNativeConstructorWhenFormatIsEmpty(): void
    {
        $result = $this->field->parse('2024-01-01');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
    }

    public function testParseUsesCreateFromFormatWhenFormatIsProvided(): void
    {
        $field = new DateField('d-m-Y');
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
        $field = new DateField('Y-m-d', outputClass: MyDate::class);
        $result = $field->parse('2024-01-01');

        self::assertInstanceOf(MyDate::class, $result);
        self::assertSame('2024-01-01', $result->format('Y-m-d'));
        self::assertSame(MyDate::class, $field->metadata()->get('class'));
        self::assertSame('Y-m-d', $field->metadata()->get('format'));
        self::assertSame('UTC', $field->metadata()->get('timezone'));
    }
}

interface MyDateInterface extends DateTimeInterface
{
}

class MyDate extends DateTimeImmutable implements MyDateInterface
{
}
