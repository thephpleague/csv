<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\StreamIterator;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileObject;

/**
 * @group stream
 */
class StreamIteratorTest extends PHPUnit_Framework_TestCase
{
    protected $csv;

    public function setUp()
    {
        $this->csv = Reader::createFromStream(fopen(__DIR__.'/data/prenoms.csv', 'r'));
        $this->csv->setDelimiter(';');
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    /**
     * @expectedException LogicException
     */
    public function testCloningIsForbidden()
    {
        $toto = clone new StreamIterator(fopen('php://temp', 'r+'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateFromStreamWithInvalidParameter()
    {
        $path = __DIR__.'/data/foo.csv';
        Reader::createFromStream($path);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateStreamWithInvalidParameter()
    {
        $path = __DIR__.'/data/foo.csv';
        new StreamIterator($path);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        new StreamIterator(fopen('php://stdin', 'r'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetCsvControlTriggersException()
    {
        (new StreamIterator(fopen('php://temp', 'r+')))->setCsvControl('toto');
    }

    /**
     * @param $expected
     * @dataProvider validBOMSequences
     */
    public function testStripBOM($expected, $res)
    {
        $fp = fopen('php://temp', 'r+');
        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }
        $csv = Reader::createFromStream($fp);

        $this->assertSame($res, $csv->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [[
                [Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
            'withDoubleBOM' =>  [[
                [Reader::BOM_UTF16_LE.Reader::BOM_UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], Reader::BOM_UTF16_LE.'john'],
            'withoutBOM' => [[
                ['john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
        ];
    }

    public function testToString()
    {
        $fp = fopen('php://temp', 'r+');
        $csv = Writer::createFromStream($fp);

        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        foreach ($expected as $row) {
            $csv->insertOne($row);
        }

        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, $csv->__toString());
    }

    public function testIteratorWithLines()
    {
        $fp = fopen('php://temp', 'r+');
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
        ];

        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }

        $stream = new StreamIterator($fp);
        $stream->setFlags(SplFileObject::READ_AHEAD);
        $stream->rewind();
        $stream->current();
        $this->assertInternalType('string', $stream->fgets());
    }

    public function testCustomNewline()
    {
        $fp = fopen('php://temp', 'r+');
        $csv = Writer::createFromStream($fp);
        $this->assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");

        $csv->insertOne(['jane', 'doe']);
        $this->assertSame("jane,doe\r\n", (string) $csv);
    }

    /**
     * @expectedException \LogicException
     */
    public function testStreamIteratorSeekThrowException()
    {
        $fp = fopen('php://temp', 'r+');
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
        ];

        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }

        $stream = new StreamIterator($fp);
        $stream->seek(-1);
    }

    public function testStreamIteratorSeek()
    {
        $fp = fopen('php://temp', 'r+');
        $expected = [
            [],
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
            [],
        ];

        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }

        $stream = new StreamIterator($fp);
        $stream->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD);
        $stream->seek(1);
        $this->assertSame($expected[1], $stream->current());
    }
}