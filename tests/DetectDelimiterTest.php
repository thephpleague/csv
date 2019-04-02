<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public function testDetectDelimiterListWithInvalidRowLimit(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        self::expectException(Exception::class);
        delimiter_detect($csv, [','], -4);
    }

    public function testDetectDelimiterListWithInvalidDelimiter(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        self::expectException(TypeError::class);
        delimiter_detect($csv, [',', []]);
    }

    public function testDetectDelimiterListWithNoCSV(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        self::assertSame(['toto' => 0, '|' => 0], delimiter_detect($csv, ['toto', '|'], 5));
    }

    public function testDetectDelimiterWithNoValidDelimiter(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        self::assertSame(['toto' => 0], delimiter_detect($csv, ['toto'], 5));
    }

    public function testDetectDelimiterListWithInconsistentCSV(): void
    {
        $data = new SplTempFileObject();
        $data->setCsvControl(';');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->setCsvControl('|');
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);
        $data->fputcsv(['toto', 'tata', 'tutu']);

        $csv = Reader::createFromFileObject($data);
        self::assertSame(['|' => 12, ';' => 4], delimiter_detect($csv, ['|', ';'], 5));
    }

    public function testDetectDelimiterKeepOriginalDelimiter(): void
    {
        $file = new SplTempFileObject();
        $file->fwrite("How are you today ?\nI'm doing fine thanks!");
        $csv = Reader::createFromFileObject($file);
        $csv->setDelimiter('@');
        $res = delimiter_detect($csv, ['toto', '|'], 5);
        self::assertSame('@', $csv->getDelimiter());
    }
}
