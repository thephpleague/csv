<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use const PHP_EOL;
use const STREAM_FILTER_READ;
use const STREAM_FILTER_WRITE;
use function chr;
use function function_exists;
use function iterator_to_array;
use function League\Csv\is_iterable as CSVIsiterable;
use function strtolower;
use function tmpfile;
use function unlink;

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
     * @covers ::__construct
     */
    public function testCreateFromFileObjectPreserveFileObjectCsvControls()
    {
        $delimiter = "\t";
        $enclosure = '?';
        $escape = '^';
        $file = new SplTempFileObject();
        $file->setCsvControl($delimiter, $enclosure, $escape);
        $obj = Reader::createFromFileObject($file);
        self::assertSame($delimiter, $obj->getDelimiter());
        self::assertSame($enclosure, $obj->getEnclosure());
        if (3 === count($file->getCsvControl())) {
            self::assertSame($escape, $obj->getEscape());
        }
    }

    /**
     * @covers ::createFromPath
     * @covers League\Csv\Stream
     */
    public function testCreateFromPathThrowsRuntimeException()
    {
        self::expectException(Exception::class);
        Reader::createFromPath(__DIR__.'/foo/bar', 'r');
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
        self::assertSame($expected, Reader::createFromString($str)->setDelimiter($delimiter)->getInputBOM());
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
        self::expectException(Exception::class);
        clone $this->csv;
    }

    /**
     * @runInSeparateProcess
     * @covers ::output
     * @covers ::sendHeaders
     */
    public function testOutputSize()
    {
        self::assertSame(60, $this->csv->output('test.csv'));
    }

    /**
     * @covers ::output
     * @covers ::sendHeaders
     */
    public function testInvalidOutputFile()
    {
        self::expectException(Exception::class);
        $this->csv->output('invalid/file.csv');
    }

    /**
     * @runInSeparateProcess
     * @covers ::output
     * @covers ::sendHeaders
     * @covers ::createFromString
     * @covers League\Csv\Stream
     */
    public function testOutputHeaders()
    {
        if (!function_exists('xdebug_get_headers')) {
            self::markTestSkipped(sprintf('%s needs the xdebug extension to run', __METHOD__));
        }

        $raw_csv = Reader::BOM_UTF8."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv->output('tést.csv');
        $headers = \xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        self::assertContains('content-type: text/csv', strtolower($headers[0]));
        self::assertSame('Content-Transfer-Encoding: binary', $headers[1]);
        self::assertSame('Content-Description: File Transfer', $headers[2]);
        self::assertContains('Content-Disposition: attachment; filename="tst.csv"; filename*=utf-8\'\'t%C3%A9st.csv', $headers[3]);
    }

    /**
     * @covers ::__toString
     * @covers ::getContent
     */
    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        self::assertSame($expected, $this->csv->__toString());
    }

    /**
     * @covers ::chunk
     */
    public function testChunkTriggersException()
    {
        self::expectException(Exception::class);
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
        foreach ($csv->chunk(32) as $chunk) {
            $res .= $chunk;
        }
        self::assertSame($expected, $res);
    }

    public function testStreamFilterMode()
    {
        self::assertSame(STREAM_FILTER_READ, Reader::createFromString('')->getStreamFilterMode());
        self::assertSame(STREAM_FILTER_WRITE, Writer::createFromString('')->getStreamFilterMode());
    }

    /**
     * @covers ::getDelimiter
     * @covers ::setDelimiter
     */
    public function testDelimeter()
    {
        self::expectException(Exception::class);
        $this->csv->setDelimiter('o');
        self::assertSame('o', $this->csv->getDelimiter());
        self::assertSame($this->csv, $this->csv->setDelimiter('o'));
        $this->csv->setDelimiter('foo');
    }

    /**
     * @covers ::getOutputBOM
     * @covers ::setOutputBOM
     */
    public function testBOMSettings()
    {
        self::assertSame('', $this->csv->getOutputBOM());
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        self::assertSame(Reader::BOM_UTF8, $this->csv->getOutputBOM());
        $this->csv->setOutputBOM('');
        self::assertSame('', $this->csv->getOutputBOM());
    }

    /**
     * @covers ::setOutputBOM
     * @covers ::getContent
     */
    public function testAddBOMSequences()
    {
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $expected = chr(239).chr(187).chr(191).'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        self::assertSame($expected, $this->csv->getContent());
    }

    /**
     * @covers ::setOutputBOM
     * @covers ::getContent
     */
    public function testChangingBOMOnOutput()
    {
        $text = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString(Reader::BOM_UTF32_BE.$text);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        self::assertSame(Reader::BOM_UTF8.$text, $reader->getContent());
    }

    /**
     * @covers ::getEscape
     * @covers ::setEscape
     */
    public function testEscape()
    {
        self::expectException(Exception::class);
        $this->csv->setEscape('o');
        self::assertSame('o', $this->csv->getEscape());
        self::assertSame($this->csv, $this->csv->setEscape('o'));

        $this->csv->setEscape('foo');
    }

    /**
     * @covers ::getEnclosure
     * @covers ::setEnclosure
     */
    public function testEnclosure()
    {
        self::expectException(Exception::class);
        $this->csv->setEnclosure('o');
        self::assertSame('o', $this->csv->getEnclosure());
        self::assertSame($this->csv, $this->csv->setEnclosure('o'));

        $this->csv->setEnclosure('foo');
    }

    /**
     * @covers ::addStreamFilter
     * @covers League\Csv\Stream
     */
    public function testAddStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->addStreamFilter('string.rot13');
        $csv->addStreamFilter('string.tolower');
        $csv->addStreamFilter('string.toupper');
        foreach ($csv as $row) {
            self::assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    /**
     * @covers ::supportsStreamFilter
     * @covers ::addStreamFilter
     * @covers League\Csv\Exception
     */
    public function testFailedAddStreamFilter()
    {
        self::expectException(Exception::class);
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        self::assertFalse($csv->supportsStreamFilter());
        $csv->addStreamFilter('string.toupper');
    }

    /**
     * @covers ::supportsStreamFilter
     * @covers ::addStreamFilter
     * @covers League\Csv\Stream::appendFilter
     */
    public function testFailedAddStreamFilterWithWrongFilter()
    {
        self::expectException(Exception::class);
        $csv = Writer::createFromStream(tmpfile());
        $csv->addStreamFilter('foobar.toupper');
    }

    /**
     * @covers ::hasStreamFilter
     * @covers ::supportsStreamFilter
     * @covers League\Csv\Stream
     */
    public function testStreamFilterDetection()
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        self::assertFalse($csv->hasStreamFilter($filtername));
        $csv->addStreamFilter($filtername);
        self::assertTrue($csv->hasStreamFilter($filtername));
    }

    /**
     * @covers ::__destruct
     */
    public function testClearAttachedStreamFilters()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($path);
        $csv->addStreamFilter('string.toupper');
        self::assertContains('JOHN', $csv->getContent());
        $csv = Reader::createFromPath($path);
        self::assertNotContains('JOHN', $csv->getContent());
    }

    /**
     * @covers ::addStreamFilter
     * @covers League\Csv\Stream
     */
    public function testSetStreamFilterOnWriter()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv', 'w+');
        $csv->addStreamFilter('string.toupper');
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
        self::assertContains("1,TWO,3,\"NEW\r\nLINE\"", $csv->getContent());
    }

    /**
     * @covers League\Csv\Stream
     */
    public function testSetCsvControlWithDocument()
    {
        $raw_csv = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $csv = Reader::createFromString($raw_csv);
        $csv
            ->setDelimiter(',')
            ->setEnclosure('"')
            ->setEscape('|');
        self::assertSame('|', $csv->getEscape());
    }

    /**
     * @covers \League\Csv\is_iterable
     */
    public function testLeagueCsvIsIterable()
    {
        self::assertTrue(CSVIsiterable(['foo']));
        self::assertTrue(CSVIsiterable(Reader::createFromString('')));
        self::assertTrue(CSVIsiterable((function () {
            yield 1;
        })()));
        self::assertFalse(CSVIsiterable(1));
        self::assertFalse(CSVIsiterable((object) ['foo']));
        self::assertFalse(CSVIsiterable(Writer::createFromString('')));
    }
}
