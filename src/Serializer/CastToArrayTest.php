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
     * @param 'csv'|'json'|'list' $shape
     * @param array<array-key, int|string> $expected
     */
    #[DataProvider('providesValidStringForArray')]
    public function testItCanConvertToArraygWithoutArguments(string $shape, string $type, string $input, array $expected): void
    {
        self::assertSame($expected, (new CastToArray(propertyType: '?iterable', shape:$shape, type:$type))->toVariable($input));
    }

    public static function providesValidStringForArray(): iterable
    {
        yield 'using the list shape' => [
            'shape' => 'list',
            'type' => 'string',
            'input' => '1,2,3,4',
            'expected' => ['1', '2', '3', '4'],
        ];

        yield 'using the list shape with the float type' => [
            'shape' => 'list',
            'type' => 'float',
            'input' => '1,2,3,4',
            'expected' => [1.0, 2.0, 3.0, 4.0],
        ];

        yield 'using the list shape with the int type' => [
            'shape' => 'list',
            'type' => 'int',
            'input' => '1,2,3,4',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the list shape with the bool type' => [
            'shape' => 'list',
            'type' => 'bool',
            'input' => '1,on,true,yes',
            'expected' => [true, true, true, true],
        ];

        yield 'using the json shape' => [
            'shape' => 'json',
            'type' => 'string',
            'input' => '[1,2,3,4]',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the json shape is not affected by the type argument' => [
            'shape' => 'json',
            'type' => 'iterable',
            'input' => '[1,2,3,4]',
            'expected' => [1, 2, 3, 4],
        ];

        yield 'using the csv shape' => [
            'shape' => 'csv',
            'type' => 'string',
            'input' => '"1",2,3,"4"',
            'expected' => ['1', '2', '3', '4'],
        ];

        yield 'using the csv shape with type int' => [
            'shape' => 'csv',
            'type' => 'int',
            'input' => '"1",2,3,"4"',
            'expected' => [1, 2, 3, 4],
        ];
    }

    public function testItFailsToCastAnUnsupportedType(): void
    {
        $this->expectException(MappingFailed::class);

        new CastToArray('?int');
    }

    public function testItFailsToCastInvalidJson(): void
    {
        $this->expectException(TypeCastingFailed::class);

        (new CastToArray('?iterable', null, 'json'))->toVariable('{"json":toto}');
    }

    public function testItCastNullableJsonUsingTheDefaultValue(): void
    {
        $defaultValue = ['toto'];

        self::assertSame(
            $defaultValue,
            (new CastToArray('?iterable', $defaultValue, 'json'))->toVariable(null)
        );
    }
}
