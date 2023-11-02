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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CastToArrayTest extends TestCase
{
    /**
     * @param 'csv'|'json'|'list' $type
     */
    #[DataProvider('providesValidStringForArray')]
    public function testItCanConvertToArraygWithoutArguments(string $type, string $input, array $expected): void
    {
        self::assertSame($expected, (new CastToArray($type))->toVariable($input, '?iterable'));
    }

    public static function providesValidStringForArray(): iterable
    {
        yield 'using the list type' => [
            'type' => CastToArray::TYPE_LIST,
            'input' => '1,2,3,4',
            'expected' => ['1', '2', '3', '4'],
        ];

        yield 'using the json type' => [
            'type' => CastToArray::TYPE_JSON,
            'input' => '[1,2,3,4]',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the csv type' => [
            'type' => CastToArray::TYPE_CSV,
            'input' => '"1",2,3,"4"',
            'expected' => ['1', '2', '3', '4'],
        ];
    }

    public function testItFailsToCastAnUnsupportedType(): void
    {
        self::assertFalse(CastToArray::supports('?int'));
        self::assertTrue(CastToArray::supports('array'));
        self::assertTrue(CastToArray::supports('?array'));
        self::assertTrue(CastToArray::supports('?iterable'));
        self::assertTrue(CastToArray::supports('iterable'));
    }

    public function testItFailsToCastInvalidJson(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToArray('json'))->toVariable('{"json":toto}', '?iterable');
    }
}
