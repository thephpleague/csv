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

namespace League\Csv;

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use TypeError;
use UnitEnum;

use function strtolower;

final class EnumFormatterTest extends TestCase
{
    public function test_it_can_convert_backed_enum(): void
    {
        $arr = ['city' => City::Brussels, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingValue());
        $doc->insertOne($arr);

        self::assertSame('Brussels,7000000'."\n", $doc->toString());
    }

    public function test_it_can_convert_backed_enum_using_json_serializable(): void
    {
        $arr = ['city' => City::Brussels, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingJson());
        $doc->insertOne($arr);

        self::assertSame('brussels,7000000'."\n", $doc->toString());
    }

    public function test_it_can_convert_backed_enum_using_callback(): void
    {
        $arr = ['city' => City::Brussels, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingCallback(fn (UnitEnum $value) => 'fourty-two'));
        $doc->insertOne($arr);

        self::assertSame('fourty-two,7000000'."\n", $doc->toString());
    }

    public function test_it_can_convert_backed_enum_using_name(): void
    {
        $arr = ['city' => City::KINSHASA, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingName());
        $doc->insertOne($arr);

        self::assertSame('KINSHASA,7000000'."\n", $doc->toString());
    }

    public function test_it_fails_to_convert_an_unit_enum(): void
    {
        $arr = ['city' => Pure::Foo, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingValue());

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('The Enum `'.Pure::class.'` cannot be encoded using the "value" strategy');

        $doc->insertOne($arr);
    }

    public function test_it_fails_to_convert_to_json_without_json_serializable_interface(): void
    {
        $arr = ['city' => WithoutJson::Foo, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingJson());

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('The Enum `'.WithoutJson::class.'` cannot be encoded using the "json" strategy');

        $doc->insertOne($arr);
    }

    public function test_it_uses_json_serializable_representation_to_convert_an_unit_enum(): void
    {
        $arr = ['city' => WithoutJson::Foo, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingJson());

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('The Enum `'.WithoutJson::class.'` cannot be encoded using the "json" strategy');

        $doc->insertOne($arr);
    }

    public function test_it_uses_name_representation_to_convert_an_unit_enum(): void
    {
        $arr = ['city' => Pure::Foo, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingName());
        $doc->insertOne($arr);

        self::assertSame('Foo,7000000'."\n", $doc->toString());
    }

    public function test_it_uses_callback_representation_to_convert_an_unit_enum(): void
    {
        $arr = ['city' => Pure::Foo, 'habitants' => 7_000_000];
        $doc = Writer::fromString();
        $doc->addFormatter(EnumFormatter::usingCallback(fn (UnitEnum $value) => 'fourty-two'));
        $doc->insertOne($arr);

        self::assertSame('fourty-two,7000000'."\n", $doc->toString());
    }
}

enum City: string implements JsonSerializable
{
    case Kigali = 'Kigali';
    case KINSHASA = 'Kinshasa';
    case Brussels = 'Brussels';

    public function jsonSerialize(): string
    {
        return strtolower($this->value);
    }
}

enum Pure implements JsonSerializable
{
    case Foo;
    case Bar;

    public function jsonSerialize(): string
    {
        return strtolower($this->name);
    }
}

enum WithoutJson: string
{
    case Foo = 'FOO';
    case Bar = 'BAR';
}
