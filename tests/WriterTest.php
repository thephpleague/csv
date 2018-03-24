<?php

namespace LeagueTest\Csv;

use ArrayIterator;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use stdClass;
use Traversable;
use TypeError;

/**
 * @group writer
 * @coversDefaultClass League\Csv\Writer
 */
class WriterTest extends TestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(__DIR__.'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }

    /**
     * @covers ::getFlushThreshold
     * @covers ::setFlushThreshold
     */
    public function testflushThreshold()
    {
        $this->csv->setFlushThreshold(12);
        $this->assertSame(12, $this->csv->getFlushThreshold());
    }

    /**
     * @covers ::setFlushThreshold
     */
    public function testflushThresholdThrowsException()
    {
        $this->csv->setFlushThreshold(1);
        $this->expectException(Exception::class);
        $this->csv->setFlushThreshold(0);
    }

    /**
     * @covers ::setFlushThreshold
     * @covers \League\Csv\is_nullable_int
     */
    public function testflushThresholdThrowsTypeError()
    {
        $this->expectException(TypeError::class);
        $this->csv->setFlushThreshold((object) 12);
    }

    public function testSupportsStreamFilter()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertTrue($csv->supportsStreamFilter());
        $csv->setFlushThreshold(3);
        $csv->addStreamFilter('string.toupper');
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->insertOne(['jane', 'doe', 'jane@example.com']);
        $csv->setFlushThreshold(null);
        $this->assertContains('JANE,DOE,JANE@EXAMPLE.COM', $csv->getContent());
    }

    /**
     * @covers ::insertOne
     */
    public function testInsert()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
        ];
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $this->assertContains('john,doe,john.doe@example.com', $this->csv->getContent());
    }

    /**
     * @covers ::insertOne
     */
    public function testInsertNormalFile()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv', 'a+');
        $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']);
        $this->assertContains('jane,doe,jane.doe@example.com', $csv->getContent());
    }

    /**
     * @covers ::insertOne
     */
    public function testInsertThrowsExceptionOnError()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv', 'r');
        $this->assertSame(0, $csv->insertOne(['jane', 'doe', 'jane.doe@example.com']));
    }

    /**
     * @covers ::insertAll
     */
    public function testFailedSaveWithWrongType()
    {
        $this->expectException(TypeError::class);
        $this->csv->insertAll(new stdClass());
    }

    /**
     * @covers ::insertAll
     *
     * @param array|Traversable $argument
     * @param string            $expected
     * @dataProvider dataToSave
     */
    public function testSave($argument, string $expected)
    {
        $this->csv->insertAll($argument);
        $this->assertContains($expected, $this->csv->getContent());
    }

    public function dataToSave()
    {
        $multipleArray = [
            ['john', 'doe', 'john.doe@example.com'],
        ];

        return [
            'array' => [$multipleArray, 'john,doe,john.doe@example.com'],
            'iterator' => [new ArrayIterator($multipleArray), 'john,doe,john.doe@example.com'],
        ];
    }

    public function testToString()
    {
        $fp = fopen('php://temp', 'r+');
        $csv = Writer::createFromStream($fp);
        $csv->setDelimiter('|');

        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        foreach ($expected as $row) {
            $csv->insertOne($row);
        }

        $expected = "john|doe|john.doe@example.com\njane|doe|jane.doe@example.com\n";
        $this->assertSame($expected, $csv->getContent());
        $csv = null;
        fclose($fp);
        $fp = null;
    }

    /**
     * @covers ::setNewline
     * @covers ::getNewline
     * @covers ::insertOne
     * @covers ::consolidate
     * @covers League\Csv\Stream
     */
    public function testCustomNewline()
    {
        $csv = Writer::createFromStream(tmpfile());
        $this->assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");
        $csv->insertOne(['jane', 'doe']);
        $this->assertSame("jane,doe\r\n", $csv->getContent());
        $csv = null;
    }

    public function testAddValidationRules()
    {
        $func = function (array $row) {
            return false;
        };

        $this->expectException(CannotInsertRecord::class);
        $this->csv->addValidator($func, 'func1');
        $this->csv->insertOne(['jane', 'doe']);
    }

    public function testFormatterRules()
    {
        $func = function (array $row) {
            return array_map('strtoupper', $row);
        };

        $this->csv->addFormatter($func);
        $this->csv->insertOne(['jane', 'doe']);
        $this->assertSame("JANE,DOE\n", $this->csv->getContent());
    }

    /**
     * @covers League\Csv\Stream::fseek
     */
    public function testWriterTriggerExceptionWithNonSeekableStream()
    {
        $this->expectException(Exception::class);
        $writer = Writer::createFromPath('php://output', 'w');
        $writer->setNewline("\r\n");
        $writer->insertOne(['foo', 'bar']);
    }
}
