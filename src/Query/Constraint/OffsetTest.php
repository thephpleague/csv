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

use League\Csv\Query\QueryException;
use League\Csv\Query\QueryTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OffsetTest extends QueryTestCase
{
    #[Test]
    public function it_can_filter_the_tabular_data_based_on_the_offset_value(): void
    {
        $predicate = Offset::filterOn('<', 2);
        $result = $this->stmt->where($predicate)->process($this->document);

        self::assertCount(1, $result);
    }

    #[Test]
    public function it_can_filter_the_tabular_data_based_on_the_offset_value_and_a_callback(): void
    {
        $predicate = Offset::filterOn(fn (int $key): bool => 0 === $key % 2);
        $result = $this->stmt->where($predicate)->process($this->document);

        self::assertCount(2, $result);
    }

    #[Test]
    public function it_will_throw_if_the_offset_values_are_invalidf(): void
    {
        $this->expectException(QueryException::class);

        Offset::filterOn('NOT IN', 'Dakar');
    }
}
