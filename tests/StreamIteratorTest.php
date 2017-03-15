<?php

namespace LeagueTest\Csv;

use League\Csv\BOM;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\StreamIterator;
use League\Csv\Writer;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @group stream
 */
class StreamIteratorTest extends TestCase
{
    public function testCloningIsForbidden()
    {
        $this->expectException(LogicException::class);
        $toto = clone new StreamIterator(fopen('php://temp', 'r+'));
    }

    public function testCreateStreamWithInvalidParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $path = __DIR__.'/data/foo.csv';
        new StreamIterator($path);
    }

    public function testCreateStreamWithNonSeekableStream()
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamIterator(fopen('php://stdin', 'r'));
    }

    public function testCreateStreamWithWrongResourceType()
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamIterator(curl_init());
    }

    public function testSetCsvControlTriggersException()
    {
        $this->expectException(InvalidArgumentException::class);
        (new StreamIterator(fopen('php://temp', 'r+')))->setCsvControl('toto');
    }

    public function testIterator()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];
        $fp = fopen('php://temp', 'r+');
        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }
        $stream = new StreamIterator($fp);
        $stream->setFlags(SplFileObject::READ_CSV);
        $this->assertCount(3, iterator_to_array($stream));
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

        $this->assertSame($res, (new Statement())->process($csv)->fetchAll()[0][0]);
    }

    public function validBOMSequences()
    {
        return [
            'withBOM' => [[
                [BOM::UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], 'john'],
            'withDoubleBOM' =>  [[
                [BOM::UTF16_LE.BOM::UTF16_LE.'john', 'doe', 'john.doe@example.com'],
                ['jane', 'doe', 'jane.doe@example.com'],
            ], BOM::UTF16_LE.'john'],
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

    public function testPrependFilter()
    {
        $fp = fopen('php://temp', 'r+');
        $csv = new StreamIterator($fp);

        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['jane', 'doe', 'jane.doe@example.com'],
        ];

        $csv->prependFilter('string.toupper', STREAM_FILTER_WRITE);
        foreach ($expected as $row) {
            $csv->fputcsv($row);
        }
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        foreach ($csv as $key => $row) {
            $this->assertSame(array_map('strtoupper', $expected[$key]), $row);
        }
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

    public function testStreamIteratorSeekThrowException()
    {
        $this->expectException(LogicException::class);
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
