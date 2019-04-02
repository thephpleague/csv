<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use Generator;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Statement;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use function array_reverse;
use function current;
use function in_array;
use function iterator_to_array;
use function json_encode;
use function next;
use function strcmp;
use function strlen;

/**
 * @group reader
 * @coversDefaultClass League\Csv\ResultSet
 */
class ResultSetTest extends TestCase
{
    private $csv;

    private $stmt;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    public function setUp(): void
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
        $this->stmt = new Statement();
    }

    public function tearDown(): void
    {
        $this->csv = null;
        $this->stmt = null;
    }

    /**
     * @covers League\Csv\Statement::process
     * @covers League\Csv\Statement::limit
     * @covers ::getIterator
     */
    public function testSetLimit(): void
    {
        self::assertCount(1, $this->stmt->limit(1)->process($this->csv));
    }

    /**
     * @covers League\Csv\Statement::offset
     */
    public function testSetOffsetThrowsException(): void
    {
        self::expectException(Exception::class);
        $this->stmt->offset(-1);
    }


    /**
     * @covers League\Csv\Statement::process
     * @covers League\Csv\Statement::buildOrderBy
     * @covers ::count
     * @covers ::getIterator
     */
    public function testCountable(): void
    {
        $records = $this->stmt->limit(1)->process($this->csv);
        self::assertCount(1, $records);
        self::assertInstanceOf(Generator::class, $records->getIterator());
    }

    /**
     * @covers League\Csv\Statement::limit
     * @covers League\Csv\Statement::offset
     */
    public function testStatementSameInstance(): void
    {
        $stmt_alt = $this->stmt->limit(-1)->offset(0);

        self::assertSame($stmt_alt, $this->stmt);
    }

    /**
     * @covers League\Csv\Statement::limit
     */
    public function testSetLimitThrowException(): void
    {
        self::expectException(Exception::class);
        $this->stmt->limit(-4);
    }

    /**
     * @covers League\Csv\Statement::offset
     * @covers ::__construct
     */
    public function testSetOffset(): void
    {
        self::assertContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            $this->stmt->offset(1)->process($this->csv)
        );
    }

    /**
     * @covers League\Csv\Statement::limit
     * @covers League\Csv\Statement::offset
     * @covers League\Csv\Statement::process
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
                    return !empty($record);
                })
                ->process($this->csv)
        );
    }

    public function intervalTest(): iterable
    {
        return [
            'tooHigh' => [1, 10],
            'normal' => [1, 1],
        ];
    }

    /**
     * @covers League\Csv\Statement::limit
     * @covers League\Csv\Statement::offset
     * @covers League\Csv\Statement::process
     */
    public function testIntervalThrowException(): void
    {
        self::expectException(OutOfBoundsException::class);
        iterator_to_array($this->stmt
            ->offset(1)
            ->limit(0)
            ->process($this->csv));
    }

    /**
     * @covers League\Csv\Statement::where
     */
    public function testFilter(): void
    {
        $func = function ($row) {
            return !in_array('jane', $row, true);
        };

        self::assertNotContains(
            ['jane', 'doe', 'jane.doe@example.com'],
            iterator_to_array($this->stmt->where($func)->process($this->csv), false)
        );
    }

    /**
     * @covers League\Csv\Statement::orderBy
     * @covers League\Csv\Statement::buildOrderBy
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
     * @covers League\Csv\Statement::orderBy
     * @covers League\Csv\Statement::buildOrderBy
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

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     * @covers ::__destruct
     * @covers League\Csv\MapIterator
     * @dataProvider invalidFieldNameProvider
     * @param int|string $field
     */
    public function testFetchColumnTriggersException($field): void
    {
        self::expectException(Exception::class);
        $this->csv->setHeaderOffset(0);
        $res = $this->stmt->process($this->csv)->fetchColumn($field);
        iterator_to_array($res, false);
    }

    public function invalidFieldNameProvider(): iterable
    {
        return [
            'invalid integer offset' => [24],
            'unknown column name' => ['fooBar'],
        ];
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndexByKey
     * @covers League\Csv\MapIterator
     */
    public function testFetchColumnTriggersOutOfRangeException(): void
    {
        self::expectException(Exception::class);
        $this->csv->setHeaderOffset(0);
        $res = $this->stmt->process($this->csv)->fetchColumn(-1);
        iterator_to_array($res, false);
    }

    /**
     * @covers ::getRecords
     * @covers ::getIterator
     */
    public function testFetchAssocWithRowIndex(): void
    {
        $arr = [
            ['A', 'B', 'C'],
            [1, 2, 3],
            ['D', 'E', 'F'],
            [6, 7, 8],
        ];

        $tmp = new SplTempFileObject();
        foreach ($arr as $row) {
            $tmp->fputcsv($row);
        }

        $csv = Reader::createFromFileObject($tmp);
        $csv->setHeaderOffset(2);
        self::assertContains(
            ['D' => '6', 'E' => '7', 'F' => '8'],
            iterator_to_array($this->stmt->process($csv), false)
        );
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     * @covers League\Csv\MapIterator
     */
    public function testFetchColumnWithColumnname(): void
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);
        self::assertContains('parentA', $this->stmt->process($csv)->fetchColumn('parent name'));
        self::assertContains('parentA', $this->stmt->process($csv)->fetchColumn(0));
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     * @covers League\Csv\MapIterator
     */
    public function testFetchColumn(): void
    {
        self::assertContains('john', $this->stmt->process($this->csv)->fetchColumn(0));
        self::assertContains('jane', $this->stmt->process($this->csv)->fetchColumn());
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByKey
     * @covers League\Csv\MapIterator
     */
    public function testFetchColumnInconsistentColumnCSV(): void
    {
        $raw = [
            ['john', 'doe'],
            ['lara', 'croft', 'lara.croft@example.com'],
        ];

        $file = new SplTempFileObject();
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($file);
        $res = $this->stmt->process($csv)->fetchColumn(2);
        self::assertCount(1, iterator_to_array($res));
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByKey
     * @covers League\Csv\MapIterator
     */
    public function testFetchColumnEmptyCol(): void
    {
        $raw = [
            ['john', 'doe'],
            ['lara', 'croft'],
        ];

        $file = new SplTempFileObject();
        foreach ($raw as $row) {
            $file->fputcsv($row);
        }
        $csv = Reader::createFromFileObject($file);
        $res = $this->stmt->process($csv)->fetchColumn(2);
        self::assertCount(0, iterator_to_array($res));
    }

    /**
     * @covers ::fetchOne
     */
    public function testfetchOne(): void
    {
        self::assertSame($this->expected[0], $this->stmt->process($this->csv)->fetchOne(0));
        self::assertSame($this->expected[1], $this->stmt->process($this->csv)->fetchOne(1));
        self::assertSame([], $this->stmt->process($this->csv)->fetchOne(35));
    }

    /**
     * @covers ::fetchOne
     */
    public function testFetchOneTriggersException(): void
    {
        self::expectException(Exception::class);
        $this->stmt->process($this->csv)->fetchOne(-5);
    }

    /**
     * @covers ::fetchPairs
     * @covers ::getColumnIndex
     * @dataProvider fetchPairsDataProvider
     * @param int|string $key
     * @param int|string $value
     */
    public function testFetchPairsIteratorMode($key, $value, array $expected): void
    {
        $iterator = $this->stmt->process($this->csv)->fetchPairs($key, $value);
        foreach ($iterator as $key => $value) {
            $res = current($expected);
            self::assertSame($value, $res[$key]);
            next($expected);
        }
    }

    public function fetchPairsDataProvider(): iterable
    {
        return [
            'default values' => [
                'key' => 0,
                'value' => 1,
                'expected' => [
                    ['john' => 'doe'],
                    ['jane' => 'doe'],
                ],
            ],
            'changed key order' => [
                'key' => 1,
                'value' => 0,
                'expected' => [
                    ['doe' => 'john'],
                    ['doe' => 'jane'],
                ],
            ],
        ];
    }

    /**
     * @covers ::fetchPairs
     * @covers ::getColumnIndex
     */
    public function testFetchPairsWithInvalidOffset(): void
    {
        self::assertCount(0, iterator_to_array($this->stmt->process($this->csv)->fetchPairs(10, 1), true));
    }

    /**
     * @covers ::fetchPairs
     * @covers ::getColumnIndex
     */
    public function testFetchPairsWithInvalidValue(): void
    {
        $res = $this->stmt->process($this->csv)->fetchPairs(0, 15);
        foreach ($res as $value) {
            self::assertNull($value);
        }
    }

    /**
     * @covers ::getHeader
     */
    public function testGetHeader(): void
    {
        $expected = ['firstname', 'lastname', 'email'];
        self::assertSame([], $this->stmt->process($this->csv)->getHeader());
        self::assertSame($expected, $this->stmt->process($this->csv, $expected)->getHeader());
        $this->csv->setHeaderOffset(0);
        self::assertSame($this->expected[0], $this->stmt->process($this->csv)->getHeader());
        self::assertSame($expected, $this->stmt->process($this->csv, $expected)->getHeader());
    }

    /**
     * @covers ::getRecords
     * @covers ::getIterator
     */
    public function testGetRecords(): void
    {
        $result = $this->stmt->process($this->csv);
        self::assertEquals($result->getIterator(), $result->getRecords());
    }

    /**
     * @covers ::jsonSerialize
     */
    public function testJsonSerialize(): void
    {
        $expected = [
            ['First Name', 'Last Name', 'E-mail'],
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        $tmp = new SplTempFileObject();
        foreach ($expected as $row) {
            $tmp->fputcsv($row);
        }

        $reader = Reader::createFromFileObject($tmp)->setHeaderOffset(0);
        $result = (new Statement())->offset(1)->limit(1)->process($reader);
        self::assertSame(
            '[{"First Name":"jane","Last Name":"doe","E-mail":"jane.doe@example.com"}]',
            json_encode($result)
        );
    }
}
