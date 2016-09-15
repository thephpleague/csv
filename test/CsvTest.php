<?php

namespace League\Csv\Test;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterReplace;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

/**
 * @group csv
 */
class CsvTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
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
    }

    public function testInterface()
    {
        $this->assertInstanceOf(SplFileObject::class, $this->csv->getIterator());
        $this->assertInstanceOf(IteratorAggregate::class, $this->csv);
        $this->assertInstanceOf(Writer::class, $this->csv->newWriter());
        $this->assertInstanceOf(Reader::class, $this->csv->newReader());
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputSize()
    {
        $this->assertSame(60, $this->csv->output('test.csv'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputHeaders()
    {
        if (!function_exists('xdebug_get_headers')) {
            $this->markTestSkipped();
        }
        $this->csv->output('test.csv');
        $headers = \xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        $this->assertContains('Content-type: text/csv;charset=UTF-8', $headers);
        $this->assertContains('Content-Transfer-Encoding: binary', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="test.csv"', $headers);
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, (string) $this->csv);
    }

    public function testCreateFromPathWithFilePath()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($path);
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithSplFileInfo()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithPHPWrapper()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath('php://filter/read=string.toupper/resource='.$path);
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    public function testCreateFromPathWithSplTempFileObject()
    {
        $this->expectException(InvalidArgumentException::class);
        Reader::createFromPath(new SplTempFileObject());
    }

    public function testCreateFromPathWithInvalidObject()
    {
        $this->expectException(InvalidArgumentException::class);
        Reader::createFromPath(new ArrayIterator([]));
    }

    public function testCreateFromString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $writer = Writer::createFromString($expected);
        $this->assertSame((string) $this->csv, (string) $writer);
    }

    public function testCreateFromFileObjectWithSplFileObject()
    {
        $path = __DIR__.'/data/foo.csv';
        $obj = new SplFileObject($path);
        $reader = Reader::createFromFileObject($obj);
        $this->assertInstanceof(Reader::class, $reader);
        $this->assertSame($obj, $reader->getIterator());
    }


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
     * @dataProvider validCsvControlProvider
     */
    public function testCsvControl($expected)
    {
        $this->csv->setDelimiter($expected);
        $this->csv->setEnclosure($expected);
        $this->csv->setEscape($expected);

        $this->assertSame($expected, $this->csv->getDelimiter());
        $this->assertSame($expected, $this->csv->getEnclosure());
        $this->assertSame($expected, $this->csv->getEscape());
    }

    public function validCsvControlProvider()
    {
        return [
            'single char' => ['o'],
            'non printable character' => ["\t"],
        ];
    }

    /**
     * @dataProvider invalidCsvControlProvider
     */
    public function testCsvControlThrowsInvalidArgumentException($char)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->csv->setDelimiter($char);
    }

    public function invalidCsvControlProvider()
    {
        return [
            'wrong type' => [[]],
            'too long' => ['coucou'],
            'too short' => [''],
            'unicode char' => ['💩'],
            'unicode char PHP7 notation' => ["\u{0001F4A9}"],
        ];
    }

    public function testBOMSettings()
    {
        $this->assertSame('', $this->csv->getOutputBOM());
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8, $this->csv->getOutputBOM());
        $this->csv->setOutputBOM('');
        $this->assertSame('', $this->csv->getOutputBOM());
    }

    public function testAddBOMSequences()
    {
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $expected = chr(239).chr(187).chr(191)."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, (string) $this->csv);
    }

    public function testGetBomOnInputWithNoBOM()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertEmpty(Reader::createFromString($expected)->getInputBOM());
    }

    public function testGetBomOnInputWithBOM()
    {
        $expected = Reader::BOM_UTF32_BE."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame(Reader::BOM_UTF32_BE, Reader::createFromString($expected)->getInputBOM());
    }

    public function testChangingBOMOnOutput()
    {
        $text = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $reader = Reader::createFromString(Reader::BOM_UTF32_BE.$text);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8.$text, (string) $reader);
    }

    public function testDetectDelimiterList()
    {
        $this->assertSame([',' => 4], $this->csv->fetchDelimitersOccurrence([',']));
    }

    public function testDetectDelimiterListWithInvalidRowLimit()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->csv->fetchDelimitersOccurrence([','], -4);
    }

    public function testDetectDelimiterListWithNoCSV()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Writer::createFromFileObject($file);
        $this->assertSame(['|' => 0], $csv->fetchDelimitersOccurrence(['toto', '|'], 5));
    }

    public function testDetectDelimiterListWithInconsistentCSV()
    {
        $data = new SplTempFileObject();
        $data->setCsvControl(';');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->setCsvControl('|');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);

        $csv = Writer::createFromFileObject($data);
        $this->assertSame(['|' => 12, ';' => 4], $csv->fetchDelimitersOccurrence(['|', ';'], 5));
    }

    public function testEncoding()
    {
        $expected = 'iso-8859-15';
        $this->csv->setInputEncoding($expected);
        $this->assertSame(strtoupper($expected), $this->csv->getInputEncoding());

        $this->expectException(InvalidArgumentException::class);
        $this->csv->setInputEncoding('');
    }

    public function testCustomNewline()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");
        $this->assertSame("\r\n", $csv->getNewline());
    }

    /**
     * @dataProvider appliedFlagsProvider
     */
    public function testAppliedFlags($flag, $fetch_count)
    {
        $path = __DIR__.'/data/tmp.txt';
        $obj = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        $this->assertCount($fetch_count, $reader->getIterator());
    }

    public function appliedFlagsProvider()
    {
        return [
            'NONE' => [0, 2],
            'DROP_NEW_LINE' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE, 2],
            'READ_AHEAD' => [SplFileObject::READ_AHEAD, 2],
            'SKIP_EMPTY' => [SplFileObject::SKIP_EMPTY, 2],
            'READ_AHEAD|DROP_NEW_LINE' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE, 2],
            'READ_AHEAD|SKIP_EMPTY' => [SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY, 2],
            'DROP_NEW_LINE|SKIP_EMPTY' => [SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY, 2],
            'READ_AHEAD|DROP_NEW_LINE|SKIP_EMPTY' => [SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY, 2],
        ];
    }

    public function testInitStreamFilterWithWriterStream()
    {
        $filter = 'php://filter/write=string.rot13/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['john', 'doe', 'john.doe@example.com']);
        }
    }

    public function testInitStreamFilterWithReaderStream()
    {
        $filter = 'php://filter/read=string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    public function testInitStreamFilterWithBothStream()
    {
        $filter = 'php://filter/string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    public function testInitStreamFilterWithSplFileObject()
    {
        $this->expectException(LogicException::class);
        Reader::createFromFileObject(new SplFileObject(__DIR__.'/data/foo.csv'))->getStreamFilterMode();
    }

    public function testappendStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv->getIterator() as $row) {
            $this->assertSame($row, ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']);
        }
    }

    /**
     * @expectedException LogicException
     */
    public function testFailPrependStreamFilter()
    {
        $csv = Reader::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $this->expectException(LogicException::class);
        $csv->prependStreamFilter('string.toupper');
    }

    public function testFailedapppendStreamFilter()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isActiveStreamFilter());
        $this->expectException(LogicException::class);
        $csv->appendStreamFilter('string.toupper');
    }

    public function testAddMultipleStreamFilter()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.tolower');
        $csv->prependStreamFilter('string.rot13');
        $csv->appendStreamFilter('string.toupper');
        foreach ($csv as $row) {
            $this->assertSame($row, ['WBUA', 'QBR', 'WBUA.QBR@RKNZCYR.PBZ']);
        }
    }

    public function testGetFilterPath()
    {
        $csv = Writer::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('string.rot13');
        $csv->prependStreamFilter('string.toupper');
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    public function testGetFilterPathWithAllStream()
    {
        $filter = 'php://filter/string.toupper/resource='.__DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($filter);
        $this->assertFalse($csv->getIterator()->getRealPath());
    }

    public function testSetStreamFilterWriterNewLine()
    {
        stream_filter_register(FilterReplace::FILTER_NAME.'*', '\lib\FilterReplace');
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv');
        $csv->appendStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n");
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
    }

    public function testUrlEncodeFilterParameters()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $csv->appendStreamFilter('convert.iconv.UTF-8/ASCII//TRANSLIT');
        $this->assertCount(1, $csv->select()->fetchAll());
    }

    /**
     * @dataProvider setHeaderProvider
     */
    public function testSetHeader($offset, $expected, $offset_found)
    {
        $this->csv->setHeader($offset);
        $this->assertSame($expected, $this->csv->getHeader());
        $this->assertSame($offset_found, $this->csv->getHeaderOffset());
    }

    public function setHeaderProvider()
    {
        return [
            'array' => [
                'offset' => ['one', 'two', 'three'],
                'expected' => ['one', 'two', 'three'],
                'offset_found' => null,
            ],
            'empty array' => [
                'offset' => [],
                'expected' => [],
                'offset_found' => null,
            ],
            'offset' => [
                'offset' => 1,
                'expected' => $this->expected[1],
                'offset_found' => 1,
            ],
            'null' => [
                'offset' => null,
                'expected' => [],
                'offset_found' => null,
            ],
        ];
    }

    public function testSetHeaderAutomaticTranscode()
    {
        $csv = Reader::createFromFileObject(new SplFileObject(__DIR__.'/data/prenoms.csv'));
        $csv->setInputEncoding('iso-8859-15');
        $csv->setHeader(0);
        $csv->setDelimiter(';');
        $this->assertSame(['prenoms', 'nombre', 'sexe', 'annee'], $csv->getHeader());
    }

    public function testSetHeaderWithBOM()
    {
        $expected = Reader::BOM_UTF32_BE."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $reader = Reader::createFromString($expected);
        $this->assertSame(['john', 'doe', 'john.doe@example.com'], $reader->setHeader(0)->getHeader());
    }

    /**
     * @dataProvider setHeaderProviderException
     */
    public function testSetHeaderThrowException($offset)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->csv->setHeader($offset)->getHeader();
    }

    public function setHeaderProviderException()
    {
        return [
            'invalid offset too high' => [23],
            'invalid offset too low' => [-23],
            'multidimentional array' => [[[[0]]]],
            'same string array' => [['foo', 'foo']],
        ];
    }
}
