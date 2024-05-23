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

namespace League\Csv\Query\Ordering;

use League\Csv\Query\QueryTestCase;
use PHPUnit\Framework\Attributes\Test;

final class MultiSortTest extends QueryTestCase
{
    #[Test]
    public function it_will_sort_nothing_if_no_sort_algorithm_is_provided(): void
    {
        self::assertSame(
            [...$this->document],
            [...$this->stmt->orderBy(MultiSort::all())->process($this->document)]
        );
    }

    #[Test]
    public function it_can_order_the_tabular_date_when_an_algo_is_provided(): void
    {
        $stmt = $this->stmt->orderBy(
            MultiSort::all()->append(Column::sortOn('Country', 'up'))
        );

        self::assertSame('UK', $stmt->process($this->document)->nth(4)['Country']);
    }

    #[Test]
    public function it_respect_the_fifo_order_to_apply_sorting(): void
    {
        $countryOrder = Column::sortOn('Country', 'ASC');
        $idOrder = Column::sortOn('CustomerID', 'DeSc');

        self::assertNotSame(
            $this->stmt
                ->orderBy(MultiSort::all()->append($countryOrder)->prepend($idOrder))
                ->process($this->document)->first()['Country'],
            $this->stmt
                ->orderBy(MultiSort::all()->append($idOrder)->prepend($countryOrder))
                ->process($this->document)->first()['Country']
        );
    }
}
