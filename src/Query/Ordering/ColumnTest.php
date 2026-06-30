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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SortDirection;

final class ColumnTest extends QueryTestCase
{
    #[Test]
    #[DataProvider('provideDirections')]
    public function it_normalizes_the_direction(SortDirection|string|int $direction, SortDirection $expected): void
    {
        self::assertSame($expected, Column::sortOn('Country', $direction)->direction);
    }

    public static function provideDirections(): iterable
    {
        yield 'ascending enum' => [SortDirection::Ascending, SortDirection::Ascending];
        yield 'descending enum' => [SortDirection::Descending, SortDirection::Descending];
        yield 'SORT_ASC' => [SORT_ASC, SortDirection::Ascending];
        yield 'SORT_DESC' => [SORT_DESC, SortDirection::Descending];
        yield 'Asc' => [' Asc ', SortDirection::Ascending];
        yield 'ascending' => ['ascending', SortDirection::Ascending];
        yield 'up' => ['up', SortDirection::Ascending];
        yield 'Desc' => [' Desc ', SortDirection::Descending];
        yield 'descending' => ['descending', SortDirection::Descending];
        yield 'down' => ['down', SortDirection::Descending];
    }

    #[Test]
    public function it_can_order_the_tabular_date_in_descending_order(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortOn('Country', SortDirection::Descending)
        );

        self::assertSame('UK', $stmt->process($this->document)->first()['Country']);
    }

    #[Test]
    public function it_can_order_the_tabular_date_in_ascending_order(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortOn('Country', SortDirection::Ascending)
        );

        self::assertSame('UK', $stmt->process($this->document)->nth(4)['Country']);
    }

    #[Test]
    public function it_can_order_using_a_specific_order_algo(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortOn(
                'Country',
                SortDirection::Descending,
                fn (string $first, string $second): int => strlen($first) <=> strlen($second) /* @phpstan-ignore-line */
            )
        );

        self::assertSame('Germany', $stmt->process($this->document)->first()['Country']);
    }
}
