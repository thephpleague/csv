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

namespace League\Csv\Ordering;

use League\Csv\Constraint\ContraintTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ColumnTest extends ContraintTestCase
{
    #[Test]
    public function it_can_order_the_tabular_date_in_descending_order(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortBy('Country', 'down')
        );

        self::assertSame('UK', $stmt->process($this->document)->first()['Country']);
    }

    #[Test]
    public function it_can_order_the_tabular_date_in_ascending_order(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortBy('Country', 'up')
        );

        self::assertSame('UK', $stmt->process($this->document)->nth(4)['Country']);
    }

    #[Test]
    public function it_can_order_using_a_specific_order_algo(): void
    {
        $stmt = $this->stmt->orderBy(
            Column::sortBy(
                'Country',
                'desc',
                fn (string $first, string $second): int => strlen($first) <=> strlen($second) /* @phpstan-ignore-line */
            )
        );

        self::assertSame('Germany', $stmt->process($this->document)->first()['Country']);
    }
}
