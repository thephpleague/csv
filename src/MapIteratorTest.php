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

use ArrayIterator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('csv')]
final class MapIteratorTest extends TestCase
{
    public function testMapIteratorCanActLikeArrayMapWithOneArray(): void
    {
        $array = [1, 2, 3, 4, 5];
        $iterator = new ArrayIterator($array);
        $mapper = fn (int $number): int => ($number * $number * $number);

        self::assertSame(
            array_map($mapper, $array),
            [...new MapIterator($iterator, $mapper)]
        );
    }

    public function testMapIteratorCanAccessTheIteratorKey(): void
    {
        $expected = [
            'foo' => 'foo => bar',
            'bar' => 'bar => baz',
        ];
        $iterator = new ArrayIterator(['foo' => 'bar', 'bar' => 'baz']);
        $mapper = fn (string $value, $offset): string =>  $offset.' => '.$value;

        self::assertSame($expected, [...new MapIterator($iterator, $mapper)]);
    }
}
