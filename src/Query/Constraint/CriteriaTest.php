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

namespace League\Csv\Query\Constraint;

use ArrayIterator;
use CallbackFilterIterator;
use League\Csv\Query\QueryTestCase;
use League\Csv\Query\Row;
use PHPUnit\Framework\Attributes\Test;

use const ARRAY_FILTER_USE_BOTH;

final class CriteriaTest extends QueryTestCase
{
    #[Test]
    public function it_returns_a_value_when_no_predicate_is_given(): void
    {
        self::assertSame($this->iterable, array_filter($this->iterable, Criteria::all(), ARRAY_FILTER_USE_BOTH));
        self::assertSame($this->iterable, array_filter($this->iterable, Criteria::none(), ARRAY_FILTER_USE_BOTH));
        self::assertSame([], array_filter($this->iterable, Criteria::any(), ARRAY_FILTER_USE_BOTH));
        self::assertSame([], array_filter($this->iterable, Criteria::xany(), ARRAY_FILTER_USE_BOTH));
    }

    #[Test]
    public function it_returns_a_value_when_some_predicates_are_given(): void
    {
        $predicate1 = fn (mixed $record, int $key) => Row::from($record)->value('volume') > 80;
        $predicate2 = fn (mixed $record, int $key) => Row::from($record)->value('edition') < 6;

        self::assertSame([
            1 => ['volume' => 86, 'edition' => 1],
            3 => ['volume' => 98, 'edition' => 2],
        ], array_filter($this->iterable, Criteria::all($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            5 => ['volume' => 67, 'edition' => 7],
        ], array_filter($this->iterable, Criteria::none($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
        ], array_filter($this->iterable, Criteria::any($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));

        self::assertSame([
            0 => ['volume' => 67, 'edition' => 2],
            2 => ['volume' => 85, 'edition' => 6],
            4 => ['volume' => 86, 'edition' => 6],
        ], array_filter($this->iterable, Criteria::xany($predicate1, $predicate2), ARRAY_FILTER_USE_BOTH));
    }

    #[Test]
    public function it_returns_the_inverse_when_using_an_empty_not(): void
    {
        $data = new ArrayIterator($this->iterable);

        $predicate1 = fn (mixed $record, int $key) => Row::from($record)->value('volume') > 80;
        $predicate2 = fn (mixed $record, int $key) => Row::from($record)->value('edition') < 6;

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
