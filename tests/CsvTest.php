<?php

namespace LeagueTest\Csv;

use League\Csv\Reader;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

    public function testCreateFromPathThrowsRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        Reader::createFromPath(__DIR__.'/foo/bar', 'r');
    }

    public function testCloningIsForbidden()
    {
        $this->expectException(LogicException::class);
        clone $this->csv;
    }

    /**
     * @runInSeparateProcess
     */
    public function testOutputSize()
    {
        $this->assertSame(60, $this->csv->output(__DIR__.'/data/test.csv'));
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
        $this->assertContains('content-type: text/csv', strtolower($headers[0]));
        $this->assertSame($headers[1], 'content-transfer-encoding: binary');
        $this->assertSame($headers[2], 'content-disposition: attachment; filename="test.csv"');
    }

    public function testToString()
    {
        $expected = "john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
        $this->assertSame($expected, (string) $this->csv);
    }
}
