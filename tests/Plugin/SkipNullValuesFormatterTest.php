<?php

namespace LeagueTest\Csv\Plugin;

use League\Csv\Plugin\SkipNullValuesFormatter;
use League\Csv\Writer;
use PHPUnit_Framework_TestCase;
use SplFileObject;
use SplTempFileObject;

/**
 * @group formatter
 */
class SkipNullValuesFormatterTest extends PHPUnit_Framework_TestCase
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
            ['john', 'doe', 'john.doe@example.com'],
            ['john', null, 'john.doe@example.com'],
        ];
        $formatter = new SkipNullValuesFormatter();
        $this->csv->addFormatter($formatter);
        foreach ($expected as $row) {
            $this->csv->insertOne($row);
        }

        $this->assertContains('john,john.doe@example.com', (string) $this->csv);
    }
}
