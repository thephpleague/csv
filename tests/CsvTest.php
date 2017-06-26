<?php

namespace LeagueTest\Csv;

use League\Csv\Exception\LengthException;
use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use League\Csv\Reader;
use League\Csv\Writer;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;

/**
 * @group csv
 * @coversDefaultClass League\Csv\AbstractCsv
 */
class CsvTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane', 'doe', 'jane.doe@example.com'],
    ];

    public function setUp()
    {
        $tmp = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $tmp->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($tmp);
    }

    public function tearDown()
    {
        $this->csv = null;
        @unlink(__DIR__.'/data/newline.csv');
    }

    /**
     * @covers ::createFromFileObject
     */
    public function testCreateFromFileObjectPreserveFileObjectCsvControls()
    {
        $delimiter = "\t";
        $enclosure = '?';
        $escape = '^';
        $file = new SplTempFileObject();
        $file->setCsvControl($delimiter, $enclosure, $escape);
        $obj = Reader::createFromFileObject($file);
        $this->assertSame($delimiter, $obj->getDelimiter());
        $this->assertSame($enclosure, $obj->getEnclosure());
        if (3 === count($file->getCsvControl())) {
            $this->assertSame($escape, $obj->getEscape());
        }
    }

    /**
     * @covers ::createFromPath
     * @covers League\Csv\Document
     */
    public function testCreateFromPathThrowsRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        Reader::createFromPath(__DIR__.'/foo/bar', 'r');
    }

    /**
     * @covers ::createFromStream
     */
    public function testCreateFromStreamWithInvalidParameter()
    {
        $this->expectException(TypeError::class);
        $path = __DIR__.'/data/foo.csv';
        Reader::createFromStream($path);
    }

    /**
     * @covers ::getInputBOM
     *
     * @dataProvider bomProvider
     * @param string $expected
     * @param string $str
     * @param string $delimiter
     */
    public function testGetInputBOM($expected, $str, $delimiter)
    {
        $this->assertSame($expected, Reader::createFromString($str)->setDelimiter($delimiter)->getInputBOM());
    }

    public function bomProvider()
    {
        $invalidBOM = <<<EOF
;\x00\x00\xFE\xFFworld
bonjour;planète
EOF;
        $validBOM = <<<EOF
\x00\x00\xFE\xFFworld
bonjour;planète
EOF;

        return [
            'invalid UTF32-BE' => ['', $invalidBOM, ';'],
            'valid UTF32-BE' => [Reader::BOM_UTF32_BE, $validBOM, ';'],
        ];
    }


    /**
     * @covers ::__clone
     */
    public function testCloningIsForbidden()
    {
        $this->expectException(LogicException::class);
        clone $this->csv;
    }

    /**
     * @runInSeparateProcess
     * @covers ::output
     */
    public function testOutputSize()
    {
        $this->assertSame(60, $this->csv->output(__DIR__.'/data/test.csv'));
    }

    /**
     * @runInSeparateProcess
     * @covers ::output
     * @covers ::createFromString
     * @covers League\Csv\Document
     */
    public function testOutputHeaders()
    {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped();
        }

        $raw_csv = Reader::BOM_UTF8."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv->output('test.csv');
        $headers = \xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        $this->assertContains('content-type: text/csv', strtolower($headers[0]));
        $this->assertSame($headers[1], 'Content-Transfer-Encoding: binary');
        $this->assertSame($headers[2], 'Content-Description: File Transfer');
        $this->assertSame($headers[3], 'Content-Disposition: attachment; filename="test.csv"');
    }

    /**
     * @covers ::__toString
     */
    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, (string) $this->csv);
    }

    /**
     * @covers ::chunk
     */
    public function testChunkTriggersException()
    {
        $this->expectException(OutOfRangeException::class);
        $chunk = $this->csv->chunk(0);
        iterator_to_array($chunk);
    }

    /**
     * @covers ::chunk
     */
    public function testChunk()
    {
        $raw_csv = Reader::BOM_UTF8."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv)->setOutputBOM(Reader::BOM_UTF32_BE);
        $expected = Reader::BOM_UTF32_BE."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $res = '';
        foreach ($csv->chunk(8192) as $chunk) {
            $res .= $chunk;
        }
        $this->assertSame($expected, $res);
    }

    public function testStreamFilterMode()
    {
        $this->assertSame(STREAM_FILTER_READ, Reader::createFromString('')->getStreamFilterMode());
        $this->assertSame(STREAM_FILTER_WRITE, Writer::createFromString('')->getStreamFilterMode());
    }

    /**
     * @covers ::getDelimiter
     * @covers ::setDelimiter
     * @covers ::filterControl
     */
    public function testDelimeter()
    {
        $this->expectException(LengthException::class);
        $this->csv->setDelimiter('o');
        $this->assertSame('o', $this->csv->getDelimiter());
        $this->csv->setDelimiter('foo');
    }

    /**
     * @covers ::getOutputBOM
     * @covers ::setOutputBOM
     */
    public function testBOMSettings()
    {
        $this->assertSame('', $this->csv->getOutputBOM());
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8, $this->csv->getOutputBOM());
        $this->csv->setOutputBOM('');
        $this->assertSame('', $this->csv->getOutputBOM());
    }

    /**
     * @covers ::setOutputBOM
     * @covers ::__toString
     */
    public function testAddBOMSequences()
    {
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $expected = chr(239).chr(187).chr(191).'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $this->assertSame($expected, (string) $this->csv);
    }

    /**
     * @covers ::setOutputBOM
     * @covers ::__toString
     */
    public function testChangingBOMOnOutput()
    {
        $text = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString(Reader::BOM_UTF32_BE.$text);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8.$text, (string) $reader);
    }

    /**
     * @covers ::getEscape
     * @covers ::setEscape
     */
    public function testEscape()
    {
        $this->expectException(LengthException::class);
        $this->csv->setEscape('o');
        $this->assertSame('o', $this->csv->getEscape());

        $this->csv->setEscape('foo');
    }

    /**
     * @covers ::getEnclosure
     * @covers ::setEnclosure
     */
    public function testEnclosure()
    {
        $this->expectException(LengthException::class);
        $this->csv->setEnclosure('o');
        $this->assertSame('o', $this->csv->getEnclosure());

        $this->csv->setEnclosure('foo');
    }

    /**
     * @covers ::addStreamFilter
     * @covers League\Csv\Document
     */
    public function testAddStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->addStreamFilter('string.rot13');
        $csv->addStreamFilter('string.tolower');
        $csv->addStreamFilter('string.toupper');
        foreach ($csv as $row) {
            $this->assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    /**
     * @covers ::supportsStreamFilter
     * @covers ::addStreamFilter
     * @covers League\Csv\Exception\LogicException
     */
    public function testFailedAddStreamFilter()
    {
        $this->expectException(LogicException::class);
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->supportsStreamFilter());
        $csv->addStreamFilter('string.toupper');
    }

    /**
     * @covers ::supportsStreamFilter
     * @covers ::addStreamFilter
     * @covers League\Csv\Document::appendFilter
     * @covers League\Csv\Exception\RuntimeException
     */
    public function testFailedAddStreamFilterWithWrongFilter()
    {
        $this->expectException(RuntimeException::class);
        $csv = Writer::createFromStream(tmpfile());
        $csv->addStreamFilter('foobar.toupper');
    }

    /**
     * @covers ::hasStreamFilter
     * @covers ::supportsStreamFilter
     * @covers League\Csv\Document
     */
    public function testStreamFilterDetection()
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter($filtername));
        $csv->addStreamFilter($filtername);
        $this->assertTrue($csv->hasStreamFilter($filtername));
    }

    /**
     * @covers ::__destruct
     */
    public function testClearAttachedStreamFilters()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($path);
        $csv->addStreamFilter('string.toupper');
        $this->assertContains('JOHN', (string) $csv);
        $csv = Reader::createFromPath($path);
        $this->assertNotContains('JOHN', (string) $csv);
    }

    /**
     * @covers ::addStreamFilter
     * @covers League\Csv\Document
     */
    public function testSetStreamFilterOnWriter()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv', 'w+');
        $csv->addStreamFilter('string.toupper');
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
        $this->assertContains("1,TWO,3,\"NEW\r\nLINE\"", (string) $csv);
    }

    /**
     * @covers League\Csv\Document
     */
    public function testSetCsvControlWithDocument()
    {
        $raw_csv = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape('|');
        $this->assertSame('|', $csv->getEscape());
    }

    /**
     * @covers \League\Csv\is_iterable
     */
    public function testIsIterablePolyFill()
    {
        if (!version_compare(PHP_VERSION, '7.1.0', '<')) {
            $this->markTestSkipped('Polyfill for PHP7.0');
        }

        $this->assertTrue(\League\Csv\is_iterable(['foo']));
        $this->assertTrue(\League\Csv\is_iterable(Reader::createFromString('')));
        $this->assertTrue(\League\Csv\is_iterable((function () {
            yield 1;
        })()));
        $this->assertFalse(\League\Csv\is_iterable(1));
        $this->assertFalse(\League\Csv\is_iterable((object) ['foo']));
        $this->assertFalse(\League\Csv\is_iterable(Writer::createFromString('')));
    }
}
