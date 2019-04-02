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

use League\Csv\CannotInsertRecord;
use PHPUnit\Framework\TestCase;

/**
 * @group converter
 * @coversDefaultClass League\Csv\CannotInsertRecord
 */
class CannotInsertRecordTest extends TestCase
{
    /**
     * @covers ::triggerOnInsertion
     * @covers ::getName
     * @covers ::getRecord
     */
    public function testTriggerOnInsertion(): void
    {
        $record = ['jane', 'doe', 'jane.doe@example.com'];
        $exception = CannotInsertRecord::triggerOnInsertion($record);

        self::assertSame($record, $exception->getRecord());
        self::assertSame('', $exception->getName());
        self::assertSame('Unable to write record to the CSV document', $exception->getMessage());
    }

    /**
     * @covers ::triggerOnValidation
     * @covers ::getName
     * @covers ::getRecord
     */
    public function testTriggerOnValidation(): void
    {
        $record = ['jane', 'doe', 'jane.doe@example.com'];
        $exception = CannotInsertRecord::triggerOnValidation('foo bar', $record);

        self::assertSame($record, $exception->getRecord());
        self::assertSame('foo bar', $exception->getName());
        self::assertSame('Record validation failed', $exception->getMessage());
    }
}
