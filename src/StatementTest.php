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

use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use function array_reverse;
use function in_array;
use function strcmp;
use function strlen;

#[Group('reader')]
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

    public function testSetLimit(): void
    {
        self::assertCount(1, $this->stmt->limit(1)->process($this->csv));
    }

    public function testSetLimitThrowException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->stmt->limit(-2);
    }

    public function testSetOffset(): void
    {
        self::assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt->offset(1)->process($this->csv)
        );
    }

    public function testSetOffsetThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->stmt->offset(-1);
    }

    public function testStatementSameInstance(): void
    {
        self::assertSame($this->stmt, $this->stmt->limit(-1)->offset(0));
    }

    #[DataProvider('intervalTest')]
    public function testInterval(int $offset, int $limit): void
    {
        self::assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt
                ->offset($offset)
                ->limit($limit)
                ->where(fn (array $record): bool => true)
                ->where(fn (array $record): bool => [] !== $record)
                ->process($this->csv)
        );
    }

    public static function intervalTest(): array
    {
        return [
            'tooHigh' => [1, 10],
            'normal' => [1, 1],
        ];
    }

    public function testIntervalThrowException(): void
    {
        $this->expectException(OutOfBoundsException::class);

        [...$this->stmt->offset(1)->limit(0)->process($this->csv)];
    }

    public function testFilter(): void
    {
        $func2 = fn (array $row): bool => !in_array('john', $row, true);

        $stmt = Statement::create(fn (array $row): bool => !in_array('jane', $row, true));
        $result1 = $stmt->process($this->csv);

        $result2 = $stmt->where($func2)->process($result1, ['foo', 'bar']);
        $result3 = $stmt->where($func2)->process($result2, ['foo', 'bar']);

        self::assertNotContains(['jane', 'doe', 'jane.doe@example.com'], [...$result1]);

        self::assertCount(0, $result2);
        self::assertEquals($result3, $result2);
    }

    public function testOrderBy(): void
    {
        $calculated = $this->stmt
            ->orderBy(fn (array $rowA, array $rowB): int => strcmp($rowA[0], $rowB[0]))
            ->process($this->csv);

        self::assertSame(array_reverse($this->expected), array_values([...$calculated]));
    }

    public function testOrderByWithEquity(): void
    {
        $calculated = $this->stmt
            ->orderBy(fn (array $rowA, array $rowB): int => strlen($rowA[0]) <=> strlen($rowB[0]))
            ->process($this->csv);

        self::assertSame($this->expected, array_values([...$calculated]));
    }
}
