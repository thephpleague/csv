<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use function array_reverse;
use function in_array;
use function iterator_to_array;
use function strcmp;
use function strlen;

/**
 * @group reader
 * @coversDefaultClass \League\Csv\Statement
 */
final class StatementTest extends TestCase
{
    private Reader $csv;
    private Statement $stmt;
    private array $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    protected function setUp(): void
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
        $this->stmt = Statement::create();
    }

    protected function tearDown(): void
    {
        unset($this->csv, $this->stmt);
    }

    /**
     * @covers ::filter
     * @covers ::process
     * @covers ::limit
     */
    public function testSetLimit(): void
    {
        self::assertCount(1, $this->stmt->limit(1)->process($this->csv));
    }

    /**
     * @covers ::limit
     * @covers \League\Csv\InvalidArgument::dueToInvalidLimit
     */
    public function testSetLimitThrowException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->stmt->limit(-2);
    }

    /**
     * @covers ::offset
     */
    public function testSetOffset(): void
    {
        self::assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt->offset(1)->process($this->csv)
        );
    }

    /**
     * @covers ::offset
     * @covers \League\Csv\InvalidArgument::dueToInvalidRecordOffset
     */
    public function testSetOffsetThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->stmt->offset(-1);
    }

    /**
     * @covers ::limit
     * @covers ::offset
     */
    public function testStatementSameInstance(): void
    {
        self::assertSame($this->stmt, $this->stmt->limit(-1)->offset(0));
    }

    /**
     * @covers ::limit
     * @covers ::offset
     * @covers ::process
     * @covers ::filter
     *
     * @dataProvider intervalTest
     */
    public function testInterval(int $offset, int $limit): void
    {
        self::assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt
                ->offset($offset)
                ->limit($limit)
                ->where(function (array $record): bool {
                    return true;
                })
                ->where(function (array $record): bool {
                    return [] !== $record;
                })
                ->process($this->csv)
        );
    }

    public function intervalTest(): array
    {
        return [
            'tooHigh' => [1, 10],
            'normal' => [1, 1],
        ];
    }

    /**
     * @covers ::limit
     * @covers ::offset
     * @covers ::process
     */
    public function testIntervalThrowException(): void
    {
        $this->expectException(OutOfBoundsException::class);

        iterator_to_array($this->stmt
            ->offset(1)
            ->limit(0)
            ->process($this->csv));
    }

    /**
     * @covers ::filter
     * @covers ::where
     * @covers ::create
     * @covers ::process
     */
    public function testFilter(): void
    {
        $func1 = function (array $row): bool {
            return !in_array('jane', $row, true);
        };

        $func2 = function (array $row): bool {
            return !in_array('john', $row, true);
        };

        $stmt = Statement::create($func1);
        $result1 = $stmt->process($this->csv);

        $result2 = $stmt->where($func2)->process($result1, ['foo', 'bar']);
        $result3 = $stmt->where($func2)->process($result2, ['foo', 'bar']);

        self::assertNotContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            iterator_to_array($result1, false)
        );

        self::assertCount(0, $result2);
        self::assertEquals($result3, $result2);
    }

    /**
     * @covers ::orderBy
     * @covers ::buildOrderBy
     */
    public function testOrderBy(): void
    {
        $func = function (array $rowA, array $rowB): int {
            return strcmp($rowA[0], $rowB[0]);
        };
        self::assertSame(
            array_reverse($this->expected),
            iterator_to_array($this->stmt->orderBy($func)->process($this->csv), false)
        );
    }

    /**
     * @covers ::orderBy
     * @covers ::buildOrderBy
     */
    public function testOrderByWithEquity(): void
    {
        $func = function (array $rowA, array $rowB): int {
            return strlen($rowA[0]) <=> strlen($rowB[0]);
        };

        self::assertSame(
            $this->expected,
            iterator_to_array($this->stmt->orderBy($func)->process($this->csv), false)
        );
    }
}
