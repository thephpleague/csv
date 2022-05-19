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

use Generator;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use function current;
use function in_array;
use function iterator_to_array;
use function json_encode;
use function next;

/**
 * @group reader
 * @coversDefaultClass \League\Csv\ResultSet
 */
final class ResultSetTest extends TestCase
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
     * @covers \League\Csv\Statement::process
     * @covers \League\Csv\Statement::buildOrderBy
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
     * @covers ::combineHeader
     * @covers ::getRecords
     */
    public function testFilter(): void
    {
        $func2 = fn (array $row): bool => !in_array('john', $row, true);

        $stmt = Statement::create(fn (array $row): bool => !in_array('jane', $row, true));

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
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     * @covers ::__destruct
     * @covers \League\Csv\InvalidArgument::dueToInvalidColumnIndex
     *
     * @dataProvider invalidFieldNameProvider
     */
    public function testFetchColumnTriggersException(int|string $field): void
    {
        $this->expectException(InvalidArgument::class);
        $this->csv->setHeaderOffset(0);
        $res = $this->stmt->process($this->csv)->fetchColumn($field);
        iterator_to_array($res, false);
    }

    public function invalidFieldNameProvider(): array
    {
        return [
            'invalid integer offset' => [24],
            'unknown column name' => ['fooBar'],
        ];
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndexByKey
     * @covers \League\Csv\InvalidArgument::dueToInvalidColumnIndex
     */
    public function testFetchColumnTriggersOutOfRangeException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->csv->setHeaderOffset(0);
        $res = $this->stmt->process($this->csv)->fetchColumn(-1);
        iterator_to_array($res, false);
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     */
    public function testFetchColumn(): void
    {
        self::assertContains('john', $this->stmt->process($this->csv)->fetchColumn(0));
        self::assertContains('jane', $this->stmt->process($this->csv)->fetchColumn());
    }

    public function testFetchColumnByNameTriggersException(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->csv->setHeaderOffset(0);

        iterator_to_array(
            $this->stmt->process($this->csv)->fetchColumnByName('foobar'),
            false
        );
    }

    public function testFetchColumnByOffsetTriggersException(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->csv->setHeaderOffset(0);

        iterator_to_array(
            $this->stmt->process($this->csv)->fetchColumnByOffset(24),
            false
        );
    }

    /**
     * @covers ::fetchColumnByOffset
     * @covers ::getColumnIndexByKey
     * @covers ::yieldColumn
     * @covers \League\Csv\InvalidArgument::dueToInvalidColumnIndex
     */
    public function testFetchColumnByOffsetTriggersOutOfRangeException(): void
    {
        $this->expectException(InvalidArgument::class);

        $this->csv->setHeaderOffset(0);

        iterator_to_array(
            $this->stmt->process($this->csv)->fetchColumnByOffset(-1),
            false
        );
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
     * @covers ::fetchColumnByName
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByValue
     * @covers ::getColumnIndexByKey
     * @covers ::yieldColumn
     */
    public function testFetchColumnWithColumnName(): void
    {
        $source = Reader::BOM_UTF8.'"parent name","child name","title"
            "parentA","childA","titleA"';
        $csv = Reader::createFromString($source);
        $csv->setHeaderOffset(0);

        self::assertContains('parentA', $this->stmt->process($csv)->fetchColumnByName('parent name'));
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByKey
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
        $res = $this->stmt->process($csv)->fetchColumnByOffset(2);

        self::assertCount(1, iterator_to_array($res));
    }

    /**
     * @covers ::fetchColumn
     * @covers ::getColumnIndex
     * @covers ::getColumnIndexByKey
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
        $res = $this->stmt->process($csv)->fetchColumnByOffset(2);
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
        $this->expectException(InvalidArgument::class);
        $this->stmt->process($this->csv)->fetchOne(-5);
    }

    /**
     * @covers ::fetchPairs
     * @covers ::getColumnIndex
     * @dataProvider fetchPairsDataProvider
     */
    public function testFetchPairsIteratorMode(int|string $index, int|string $item, array $expected): void
    {
        $iterator = $this->stmt->process($this->csv)->fetchPairs($index, $item);
        foreach ($iterator as $key => $value) {
            $res = current($expected);
            self::assertSame($value, $res[$key]);
            next($expected);
        }
    }

    public function fetchPairsDataProvider(): array
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
     * @covers \League\Csv\Statement::create
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
        $result = Statement::create(null, 1, 1)->process($reader);
        self::assertSame(
            '[{"First Name":"jane","Last Name":"doe","E-mail":"jane.doe@example.com"}]',
            json_encode($result)
        );
    }

    /**
     * @covers ::validateHeader
     */
    public function testHeaderThrowsExceptionOnError(): void
    {
        $this->expectException(SyntaxError::class);
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setDelimiter(',');
        $resultSet = Statement::create()->process($csv);
        Statement::create()->process($resultSet, ['foo', 'foo']);
    }

    /**
     * @covers ::validateHeader
     */
    public function testHeaderThrowsExceptionOnInvalidColumnNames(): void
    {
        $this->expectException(SyntaxError::class);
        $csv = Reader::createFromString(
            'field1,field1,field3
            1,2,3
            4,5,6'
        );
        $csv->setDelimiter(',');
        $resultSet = Statement::create()->process($csv);
        Statement::create()->process($resultSet, ['foo', 3]);
    }
}
