<?php

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use SplTempFileObject;
use TypeError;
use function League\Csv\delimiter_detect;

/**
 * @group reader
 */
class DetectDelimiterTest extends TestCase
{
    public function testDetectDelimiterListWithInvalidRowLimit()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->expectException(Exception::class);
        delimiter_detect($csv, [','], -4);
    }

    public function testDetectDelimiterListWithInvalidDelimiter()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->expectException(TypeError::class);
        delimiter_detect($csv, [',', []]);
    }

    public function testDetectDelimiterListWithNoCSV()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->assertSame(['toto' => 0, '|' => 0], delimiter_detect($csv, ['toto', '|'], 5));
    }

    public function testDetectDelimiterWithNoValidDelimiter()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $this->assertSame(['toto' => 0], delimiter_detect($csv, ['toto'], 5));
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

        $csv = Reader::createFromFileObject($data);
        $this->assertSame(['|' => 12, ';' => 4], delimiter_detect($csv, ['|', ';'], 5));
    }

    public function testDetectDelimiterKeepOriginalDelimiter()
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $csv->setDelimiter('@');
        $res = delimiter_detect($csv, ['toto', '|'], 5);
        $this->assertSame('@', $csv->getDelimiter());
    }
}
