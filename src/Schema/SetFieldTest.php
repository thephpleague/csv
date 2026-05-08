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

namespace League\Csv\Schema\Tests;

use League\Csv\Schema\FieldType;
use League\Csv\Schema\SetField;
use PHPUnit\Framework\TestCase;
use stdClass;
use ValueError;

use function array_is_list;

use const PHP_INT_MAX;

final class SetFieldTest extends TestCase
{
    public function test_it_can_be_instantiated(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertSame(',', $field->separator);
        self::assertSame(PHP_INT_MAX, $field->limit);
    }

    public function test_it_trims_the_separator(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class, ' | ');

        self::assertSame('|', $field->separator);
    }

    public function test_it_throws_when_separator_is_empty(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('The set field separator can not be an empty string.');

        SetField::fromEnum(TestSetEnum::class, '   ');
    }

    public function test_it_returns_the_correct_type(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertSame(FieldType::Set, $field->type());
    }

    public function test_it_returns_the_correct_name(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertSame(FieldType::Set->value, $field->name());
    }

    public function test_it_returns_null_for_non_string_values(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertNull($field->parse(null));
        self::assertNull($field->parse(1));
        self::assertNull($field->parse(true));
        self::assertNull($field->parse([]));
        self::assertNull($field->parse(new stdClass()));
    }

    public function test_it_returns_null_for_empty_strings(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertNull($field->parse(''));
        self::assertNull($field->parse('   '));
    }

    public function test_it_parses_a_set_value(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);

        self::assertSame([TestSetEnum::Read, TestSetEnum::Write, TestSetEnum::Delete], $field->parse('read,write,delete'));
    }

    public function test_it_respects_the_limit(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class, limit: 2);

        self::assertSame([TestSetEnum::Read, TestSetEnum::Write, TestSetEnum::Delete], $field->parse('read,write,delete'));
    }

    public function test_it_can_use_custom_separator(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class, '|');

        self::assertSame([TestSetEnum::Read, TestSetEnum::Write], $field->parse('read|write'));
    }

    public function test_it_returns_metadata(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class, '|', 3);

        self::assertSame(
            [
                'separator' => '|',
                'limit' => 3,
                'enum' => [
                    'class' => TestSetEnum::class,
                    'backedType' => 'string',
                    'cases' => [
                        ['name' => 'Read', 'value' => 'read'],
                        ['name' => 'Write', 'value' => 'write'],
                        ['name' => 'Delete', 'value' => 'delete'],
                    ],
                ],
            ],
            $field->metadata()->all()
        );
    }

    public function test_it_handles_set_with_duplicate_values(): void
    {
        $field = SetField::fromEnum(TestSetEnum::class);
        $value = $field->parse('read, write,read,,delete');

        self::assertIsArray($value);
        self::assertTrue(array_is_list($value));
        self::assertCount(3, $value);
        self::assertSame([TestSetEnum::Read, TestSetEnum::Write, TestSetEnum::Delete], $value);
    }
}

enum TestSetEnum: string
{
    case Read = 'read';
    case Write = 'write';
    case Delete = 'delete';
}
