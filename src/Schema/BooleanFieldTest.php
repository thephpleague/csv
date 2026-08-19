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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BooleanField::class)]
final class BooleanFieldTest extends TestCase
{
    private BooleanField $field;

    protected function setUp(): void
    {
        $this->field = new BooleanField();
    }

    public static function provideBooleanValues(): array
    {
        return [
            [true, true],
            [false, false],
            ['true', true],
            ['false', false],
            ['1', true],
            ['0', false],
            ['  true  ', true],
            ['', null],
            ['   ', null],
            ['foo', null],
            [[], null],
            [123, null],
        ];
    }

    #[DataProvider('provideBooleanValues')]
    public function testParse(mixed $input, ?bool $expected): void
    {
        $result = $this->field->parse($input);

        null === $expected
            ? self::assertNull($result)
            : self::assertSame($expected, $result);
    }

    public function test_metadata_contains_expected_structure(): void
    {
        $field = new BooleanField();

        self::assertTrue($field->metadata()->isEmpty());
    }
}
