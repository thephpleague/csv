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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClockField::class)]
final class ClockFieldTest extends TestCase
{
    public function test_hours_constructor_parses_correctly(): void
    {
        $field = ClockField::hours();

        self::assertSame('time(precision=hours,style=padded,separator=:)', $field->name());

        self::assertSame('10:00:00', $field->parse('10'));
        self::assertSame('23:00:00', $field->parse('23'));
    }

    public function test_minutes_constructor_parses_correctly(): void
    {
        $field = ClockField::minutes(separator: '.');

        self::assertSame('time(precision=hours_minutes,style=padded,separator=.)', $field->name());

        self::assertSame('10.30.00', $field->parse('10.30'));
        self::assertSame('23.59.00', $field->parse('23.59'));
    }

    public function test_seconds_constructor_parses_correctly(): void
    {
        $field = ClockField::seconds();

        self::assertSame('time(precision=hours_minutes_seconds,style=padded,separator=:)', $field->name());

        self::assertSame('10:30:45', $field->parse('10:30:45'));
        self::assertSame('00:00:00', $field->parse('00:00:00'));
    }

    public function test_invalid_string_returns_null(): void
    {
        $field = ClockField::seconds();

        self::assertNull($field->parse(''));
        self::assertNull($field->parse('   '));
        self::assertNull($field->parse('invalid'));
    }

    public function test_non_string_returns_null(): void
    {
        $field = ClockField::seconds();

        self::assertNull($field->parse(null));
        self::assertNull($field->parse(123));
        self::assertNull($field->parse([]));
    }

    public function test_seconds_precision_rejects_invalid_time(): void
    {
        $field = ClockField::seconds();

        self::assertNull($field->parse('25:00:00')); // invalid hour
        self::assertNull($field->parse('10:70:00')); // invalid minute
        self::assertNull($field->parse('10:00:90')); // invalid second
    }

    public function test_minutes_precision_rejects_seconds_input(): void
    {
        $field = ClockField::minutes();

        self::assertNull($field->parse('10:30:45')); // too precise
    }

    public function test_hours_precision_rejects_minutes_input(): void
    {
        $field = ClockField::hours();

        self::assertNull($field->parse('10:30')); // too precise
        self::assertNull($field->parse('10:30:45'));
    }

    public function test_output_is_always_normalized_to_his(): void
    {
        $field = ClockField::seconds(clockStyle: ClockStyle::NonPadded);

        self::assertSame('01:02:03', $field->parse('1:2:3'));
    }

    public function test_metadata_contains_format(): void
    {
        $field = ClockField::seconds();

        self::assertSame([], $field->metadata()->all());
    }

    public function test_name_contains_format(): void
    {
        self::assertSame(
            'time(precision=hours_minutes_seconds,style=padded,separator=:)',
            ClockField::seconds()->name()
        );

        self::assertSame(
            'time(precision=hours_minutes,style=padded,separator=:)',
            ClockField::minutes()->name()
        );

        self::assertSame(
            'time(precision=hours,style=padded,separator=:)',
            ClockField::hours()->name()
        );
    }
}
