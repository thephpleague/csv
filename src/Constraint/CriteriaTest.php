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

namespace League\Csv\Constraint;

use ArrayIterator;
use CallbackFilterIterator;
use PHPUnit\Framework\Attributes\Test;

use PHPUnit\Framework\TestCase;

use const ARRAY_FILTER_USE_BOTH;

final class CriteriaTest extends TestCase
{
    #[Test]
    public function it_returns_a_value_when_no_predicate_is_given(): void
    {
        $data = [
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
            ['volume' => 67, 'edition' => 7],
        ];

        self::assertSame($data, array_filter($data, Criteria::all(), ARRAY_FILTER_USE_BOTH));
        self::assertSame($data, array_filter($data, Criteria::none(), ARRAY_FILTER_USE_BOTH));
        self::assertSame([], array_filter($data, Criteria::any(), ARRAY_FILTER_USE_BOTH));
        self::assertSame([], array_filter($data, Criteria::xany(), ARRAY_FILTER_USE_BOTH));
    }

    #[Test]
    public function it_returns_a_value_when_some_predicates_are_given(): void
    {
        $data = [
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
            ['volume' => 67, 'edition' => 7],
        ];

        $predicate1 = (fn (array $record, int $key) => $record['volume'] > 80);
        $predicate2 = (fn (array $record, int $key) => $record['edition'] < 6);

        self::assertSame([
            1 => ['volume' => 86, 'edition' => 1],
            3 => ['volume' => 98, 'edition' => 2],
        ], array_filter($data, Criteria::all($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            5 => ['volume' => 67, 'edition' => 7],
        ], array_filter($data, Criteria::none($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
        ], array_filter($data, Criteria::any($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            0 => ['volume' => 67, 'edition' => 2],
            2 => ['volume' => 85, 'edition' => 6],
            4 => ['volume' => 86, 'edition' => 6],
        ], array_filter($data, Criteria::xany($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));
    }

    #[Test]
    public function it_returns_the_inverse_when_using_an_empty_not(): void
    {
        $data = new ArrayIterator([
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
            ['volume' => 67, 'edition' => 7],
        ]);

        $predicate1 = (fn (array $record, int $key) => $record['volume'] > 80);
        $predicate2 = (fn (array $record, int $key) => $record['edition'] < 6);

        self::assertSame([
            0 => ['volume' => 67, 'edition' => 2],
            2 => ['volume' => 85, 'edition' => 6],
            4 => ['volume' => 86, 'edition' => 6],
            5 => ['volume' => 67, 'edition' => 7],
        ], iterator_to_array(new CallbackFilterIterator($data, Criteria::all($predicate1, $predicate2)->not())));

        self::assertSame([
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
        ], iterator_to_array(new CallbackFilterIterator($data, Criteria::none($predicate1, $predicate2)->not())));

        self::assertSame([
            5 => ['volume' => 67, 'edition' => 7],
        ], iterator_to_array(new CallbackFilterIterator($data, Criteria::any($predicate1, $predicate2)->not())));

        self::assertSame([
           1 => ['volume' => 86, 'edition' => 1],
           3 => ['volume' => 98, 'edition' => 2],
           5 => ['volume' => 67, 'edition' => 7],
        ], iterator_to_array(new CallbackFilterIterator($data, Criteria::xany($predicate1, $predicate2)->not()), true));
    }
}
