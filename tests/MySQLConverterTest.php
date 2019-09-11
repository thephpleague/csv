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

use League\Csv\MySQLConverter;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass \League\Csv\MySQLConverter
 */
class MySQLConverterTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::convert
     */
    public function testToMySQL()
    {
        $csv = Reader::createFromPath(__DIR__.'/data/sql.csv', 'r')
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        $headers = array_unique($csv->getHeader());

        $converter = new MySQLConverter('test', 'test');
        $records = $csv->getRecords($headers);

        $sql = $converter->convert($records);

        self::assertContains('DROP TABLE IF EXISTS', $sql);
        self::assertContains('CREATE TABLE', $sql);
        self::assertContains('INSERT INTO ', $sql);
        self::assertContains('USE `test`;', $sql);
    }
}
