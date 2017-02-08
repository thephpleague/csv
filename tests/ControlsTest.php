<?php

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\Writer;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group stream
 * @group csv
 */
class ControlsTest extends TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $csv = new SplTempFileObject();
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = Reader::createFromFileObject($csv, "\n");
    }

    public function tearDown()
    {
        $this->csv = null;
    }

    public function testDelimeter()
    {
        $this->expectException(Exception::class);
        $this->csv->setDelimiter('o');
        $this->assertSame('o', $this->csv->getDelimiter());
        $this->csv->setDelimiter('foo');
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
        $expected = chr(239).chr(187).chr(191).'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $this->assertSame($expected, (string) $this->csv);
    }

    public function testGetBomOnInputWithNoBOM()
    {
        $expected = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertNotContains(Reader::BOM_UTF8, (string) $reader);
    }

    public function testChangingBOMOnOutput()
    {
        $text = 'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $reader = Reader::createFromString(Reader::BOM_UTF32_BE.$text);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8.$text, (string) $reader);
    }

    public function testDetectDelimiterList()
    {
        $this->assertSame([',' => 4], $this->csv->fetchDelimitersOccurrence([',']));
    }

    public function testEscape()
    {
        $this->expectException(Exception::class);
        $this->csv->setEscape('o');
        $this->assertSame('o', $this->csv->getEscape());

        $this->csv->setEscape('foo');
    }

    public function testEnclosure()
    {
        $this->expectException(Exception::class);
        $this->csv->setEnclosure('o');
        $this->assertSame('o', $this->csv->getEnclosure());

        $this->csv->setEnclosure('foo');
    }

    /**
     * @dataProvider appliedFlagsProvider
     */
    public function testAppliedFlags($flag, $fetch_count)
    {
        $path = __DIR__.'/data/tmp.txt';
        $obj  = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        $this->assertCount($fetch_count, $reader->select()->fetchAll());
        $reader = null;
        $obj = null;
        unlink($path);
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

    public function testFailedAddStreamFilter()
    {
        $this->expectException(LogicException::class);
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertFalse($csv->isStream());
        $csv->addStreamFilter('string.toupper');
    }

    public function testStreamFilterDetection()
    {
        $filtername = 'string.toupper';
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter($filtername));
        $csv->addStreamFilter($filtername);
        $this->assertTrue($csv->hasStreamFilter($filtername));
    }

    public function testClearAttachedStreamFilters()
    {
        $path = __DIR__.'/data/foo.csv';
        $csv = Reader::createFromPath($path);
        $csv->addStreamFilter('string.toupper');
        $this->assertContains('JOHN', (string) $csv);
        $csv = Reader::createFromPath($path);
        $this->assertNotContains('JOHN', (string) $csv);
    }

    public function testRemoveStreamFilters()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/foo.csv');
        $this->assertFalse($csv->hasStreamFilter('string.tolower'));
    }

    public function testSetStreamFilterWriterNewLine()
    {
        stream_filter_register(FilterReplace::FILTER_NAME.'*', FilterReplace::class);
        $csv = Writer::createFromPath(__DIR__.'/data/newline.csv');
        $csv->addStreamFilter(FilterReplace::FILTER_NAME."\r\n:\n");
        $csv->insertOne([1, 'two', 3, "new\r\nline"]);
        $this->assertContains("1,two,3,\"new\nline\"", (string) $csv);
    }
}
