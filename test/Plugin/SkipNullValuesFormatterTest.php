<?php

namespace League\Csv\Test\Plugin;

use League\Csv\Plugin\SkipNullValuesFormatter;
use League\Csv\Test\AbstractTestCase;
use League\Csv\Writer;
use LimitIterator;
use SplFileObject;
use SplTempFileObject;

/**
 * @group formatter
 */
class SkipNullValuesFormatterTest extends AbstractTestCase
{
    private $csv;

    public function setUp()
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    public function tearDown()
    {
        $csv = new SplFileObject(dirname(__DIR__).'/data/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        $this->csv = null;
    }


    public function testInsertNullToSkipCell()
    {
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            'john,doe,john.doe@example.com',
            ['john', null, 'john.doe@example.com'],
        ];
        $formatter = new SkipNullValuesFormatter();
        $this->csv->addFormatter($formatter);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }
        $iterator = new LimitIterator($this->csv->getIterator(), 2, 1);
        $iterator->rewind();
        $res = $iterator->getInnerIterator()->current();
        $this->assertSame(['john', 'john.doe@example.com'], $res);
    }
}
