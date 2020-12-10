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

use League\Csv\CannotInsertRecord;
use League\Csv\ColumnConsistency;
use League\Csv\Exception;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group writer
 * @coversDefaultClass \League\Csv\ColumnConsistency
 */
class ColumnConsistencyTest extends TestCase
{
    /** @var Writer  */
    private $csv;

    public function setUp(): void
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown(): void
    {
        $csv = new SplFileObject(__DIR__.'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        unset($this->csv);
    }

    /**
     * @covers ::__construct
     * @covers ::getColumnCount
     * @covers ::__invoke
     * @covers League\Csv\CannotInsertRecord
     */
    public function testAutoDetect(): void
    {
        try {
            $expected = ['jane', 'jane.doe@example.com'];
            $validator = new ColumnConsistency();
            $this->csv->addValidator($validator, 'consistency');
            self::assertSame(-1, $validator->getColumnCount());
            $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
            self::assertSame(3, $validator->getColumnCount());
            $this->csv->insertOne($expected);
        } catch (CannotInsertRecord $e) {
            self::assertSame($e->getName(), 'consistency');
            self::assertEquals($e->getRecord(), ['jane', 'jane.doe@example.com']);
        }
    }

    /**
     * @covers ::__construct
     * @covers ::__invoke
     * @covers League\Csv\CannotInsertRecord
     */
    public function testColumnsCount(): void
    {
        $this->expectException(CannotInsertRecord::class);
        $this->csv->addValidator(new ColumnConsistency(3), 'consistency');
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    /**
     * @covers ::__construct
     * @covers League\Csv\Exception
     */
    public function testColumsCountTriggersException(): void
    {
        $this->expectException(Exception::class);
        new ColumnConsistency(-2);
    }
}
