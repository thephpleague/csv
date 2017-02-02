<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group csv
 */
class ControlsTest extends PHPUnit_Framework_TestCase
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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The delimiter must be a single character
     */
    public function testDelimeter()
    {
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFflusThreshold()
    {
        $this->csv->setFlushThreshold(12);
        $this->assertSame(12, $this->csv->getFlushThreshold());
        $this->csv->setFlushThreshold(-1);
    }

    public function testAddBOMSequences()
    {
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $expected = chr(239).chr(187).chr(191).'john,doe,john.doe@example.com'.PHP_EOL
            .'jane,doe,jane.doe@example.com'.PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The number of rows to consider must be a valid positive integer
     */
    public function testDetectDelimiterListWithInvalidRowLimit()
    {
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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The escape must be a single character
     */
    public function testEscape()
    {
        $this->csv->setEscape('o');
        $this->assertSame('o', $this->csv->getEscape());

        $this->csv->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The enclosure must be a single character
     */
    public function testEnclosure()
    {
        $this->csv->setEnclosure('o');
        $this->assertSame('o', $this->csv->getEnclosure());

        $this->csv->setEnclosure('foo');
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
        $obj  = new SplFileObject($path, 'w+');
        $obj->fwrite("1st\n2nd\n");
        $obj->setFlags($flag);
        $reader = Reader::createFromFileObject($obj);
        $this->assertCount($fetch_count, $reader->fetchAll());
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
}
