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

final class TwoColumnsTest extends QueryTestCase
{
    #[Test]
    public function it_can_filter_the_tabular_data_based_on_the_column_value(): void
    {
        $predicate = TwoColumns::filterOn('Country', '=', 'City');
        $result = $this->stmt->where($predicate)->process($this->document);

        self::assertCount(0, $result);
    }

    #[Test]
    public function it_can_filter_the_tabular_data_based_on_the_column_value_and_the_column_offset(): void
    {
        $predicate = TwoColumns::filterOn(0, 'contains', 0);
        $result = $this->stmt->where($predicate)->process($this->document);

        self::assertCount(5, $result);
    }

    public function it_will_throw_if_the_column_does_not_exist(): void
    {
        $predicate = TwoColumns::filterOn('City', '=', 'Dakar');
        $this->expectExceptionObject(QueryException::dueToUnknownColumn('City', []));

        $this->stmt->where($predicate)->process($this->document);
    }
}
