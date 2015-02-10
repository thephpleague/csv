<?php

namespace League\Csv\test;

use DateTime;
use League\Csv\Reader;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;

date_default_timezone_set('UTC');

/**
 * @group controls
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

    public function testDetectDelimiterList()
    {
        $this->assertSame([4 => ','], $this->csv->detectDelimiterList());
    }

    public function testBOMSettings()
    {
        $this->assertNull($this->csv->getOutputBOM());
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8, $this->csv->getOutputBOM());
        $this->csv->setOutputBOM();
        $this->assertNull($this->csv->getOutputBOM());
    }

    public function testAddBOMSequences()
    {
        $this->csv->setOutputBOM(Reader::BOM_UTF8);
        $expected = chr(239).chr(187).chr(191)."john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
    }

    public function testGetBomOnInputWithNoBOM()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertEmpty($reader->getInputBOM());
    }

    public function testGetBomOnInputWithBOM()
    {
        $expected = Reader::BOM_UTF32_BE."john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $reader = Reader::createFromString($expected);
        $this->assertSame(Reader::BOM_UTF32_BE, $reader->getInputBOM());
        $this->assertSame(Reader::BOM_UTF32_BE, $reader->getInputBOM());
    }

    public function testChangingBOMOnOutput()
    {
        $text = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $reader = Reader::createFromString(Reader::BOM_UTF32_BE.$text);
        $reader->setOutputBOM(Reader::BOM_UTF8);
        $this->assertSame(Reader::BOM_UTF8.$text, $reader->__toString());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage `$nb_rows` must be a valid positive integer
     */
    public function testDetectDelimiterListWithInvalidRowLimit()
    {
        $this->csv->detectDelimiterList(-4);
    }

    public function testDetectDelimiterListWithNoCSV()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Writer::createFromFileObject($file);
        $this->assertSame([], $csv->detectDelimiterList(5, ['toto', '|']));
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
        $this->assertSame([12 => '|', 4 => ';'], $csv->detectDelimiterList(5, ['|']));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The escape character must be a single character
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

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage you should use a valid charset
     */
    public function testEncoding()
    {
        $expected = 'iso-8859-15';
        $this->csv->setEncodingFrom($expected);
        $this->assertSame(strtoupper($expected), $this->csv->getEncodingFrom());

        $this->csv->setEncodingFrom('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage you should use a `SplFileObject` Constant
     */
    public function testSetFlags()
    {
        $this->assertSame(SplFileObject::READ_CSV, $this->csv->getFlags() & SplFileObject::READ_CSV);
        $this->assertSame(SplFileObject::DROP_NEW_LINE, $this->csv->getFlags() & SplFileObject::DROP_NEW_LINE);
        $this->csv->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $this->csv->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $this->csv->getFlags() & SplFileObject::READ_CSV);
        $this->assertSame(SplFileObject::DROP_NEW_LINE, $this->csv->getFlags() & SplFileObject::DROP_NEW_LINE);

        $this->csv->setFlags(-3);
    }

    public function testCustomNewline()
    {
        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $this->assertSame("\n", $csv->getNewline());
        $csv->setNewline("\r\n");
        $this->assertSame("\r\n", $csv->getNewline());
    }
}
