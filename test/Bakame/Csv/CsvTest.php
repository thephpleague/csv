<?php

namespace Bakame\Csv;

use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use PHPUnit_Framework_TestCase;

class CsvTest extends PHPUnit_Framework_TestCase
{
    private $csv;

    private $expected = [
        ['john', 'doe', 'john.doe@example.com'],
        ['jane','doe','jane.doe@example.com'],
    ];

    public function setUp()
    {
        $csv = new SplTempFileObject;
        foreach ($this->expected as $row) {
            $csv->fputcsv($row);
        }

        $this->csv = new Csv($csv);
    }

    public function testConstructorWithFileObject()
    {
        $path = __DIR__.'/foo.csv';

        $csv = new Csv(new SplFileInfo($path));
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    public function testConstructorWithFilePath()
    {
        $path = __DIR__.'/foo.csv';

        $csv = new Csv($path);
        $this->assertSame($path, $csv->getIterator()->getRealPath());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithNotWritablePath()
    {
        new Csv('/usr/bin/foo.csv');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithWrongType()
    {
        new Csv(['/usr/bin/foo.csv']);
    }

    public function testCreateFromString()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        foreach (Csv::createFromString($expected) as $key => $row) {
            $this->assertSame($this->expected[$key], $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDelimeter()
    {
        $this->csv->setDelimiter('o');
        $this->assertSame('o', $this->csv->getDelimiter());

        $this->csv->setDelimiter('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEscape()
    {
        $this->csv->setEscape('o');
        $this->assertSame('o', $this->csv->getEscape());

        $this->csv->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnclosure()
    {
        $this->csv->setEnclosure('o');
        $this->assertSame('o', $this->csv->getEnclosure());

        $this->csv->setEnclosure('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFailCreateFromString()
    {
        Reader::createFromString(new \DateTime);
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com".PHP_EOL
            ."jane,doe,jane.doe@example.com".PHP_EOL;
        $this->assertSame($expected, $this->csv->__toString());
    }

    public function testIterator()
    {
        foreach ($this->csv as $key => $row) {
            $this->assertSame($this->expected[$key], $row);
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetFlags()
    {
        $this->csv->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $this->csv->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $this->csv->getFlags() & SplFileObject::READ_CSV);

        $this->csv->setFlags(-3);
    }

    public function testToHTML()
    {
        $expected = <<<EOF
<table class="table-csv-data">
<tr>
<td>john</td>
<td>doe</td>
<td>john.doe@example.com</td>
</tr>
<tr>
<td>jane</td>
<td>doe</td>
<td>jane.doe@example.com</td>
</tr>
</table>
EOF;
        $this->assertSame($expected, $this->csv->toHTML());
    }

    public function testJsonInterface()
    {
        $this->assertSame(json_encode($this->expected), json_encode($this->csv));
    }
}
