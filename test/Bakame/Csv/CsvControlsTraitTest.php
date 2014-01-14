<?php

namespace Bakame\Csv;

use SplFileObject;

class CsvControlsTraitTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException InvalidArgumentException
     */
    public function testDelimeter()
    {
        $control = $this->getObjectForTrait('\Bakame\Csv\CsvControlsTrait');
        $control->setDelimiter('o');
        $this->assertSame('o', $control->getDelimiter());

        $control->setDelimiter('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEscape()
    {
        $control = $this->getObjectForTrait('\Bakame\Csv\CsvControlsTrait');
        $control->setEscape('o');
        $this->assertSame('o', $control->getEscape());

        $control->setEscape('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEnclosure()
    {
        $control = $this->getObjectForTrait('\Bakame\Csv\CsvControlsTrait');
        $control->setEnclosure('o');
        $this->assertSame('o', $control->getEnclosure());

        $control->setEnclosure('foo');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetFlags()
    {
        $control = $this->getObjectForTrait('\Bakame\Csv\CsvControlsTrait');
        $control->setFlags(SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::SKIP_EMPTY, $control->getFlags() & SplFileObject::SKIP_EMPTY);
        $this->assertSame(SplFileObject::READ_CSV, $control->getFlags() & SplFileObject::READ_CSV);

        $control->setFlags(-3);
    }
}
