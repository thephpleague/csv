<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;

#[Group('writer')]
final class ColumnConsistencyTest extends TestCase
{
    private Writer $csv;

    protected function setUp(): void
    {
        $this->csv = Writer::createFromFileObject(new SplTempFileObject());
    }

    protected function tearDown(): void
    {
        $csv = new SplFileObject(__DIR__.'/../test_files/foo.csv', 'w');
        $csv->setCsvControl();
        $csv->fputcsv(['john', 'doe', 'john.doe@example.com'], ',', '"');
        unset($this->csv);
    }

    public function testAutoDetect(): void
    {
        try {
            $expected = ['jane', 'jane.doe@example.com'];
            $validator = new ColumnConsistency();
            $this->csv->addValidator($validator, 'consistency');
            self::assertSame(-1, $validator->getColumnCount());
            $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
            self::assertSame(3, $validator->getColumnCount());
            $this->csv->insertOne($expected);
        } catch (CannotInsertRecord $exception) {
            self::assertSame('consistency', $exception->getName());
            self::assertEquals(['jane', 'jane.doe@example.com'], $exception->getRecord());
        }
    }

    public function testColumnsCount(): void
    {
        $this->expectException(CannotInsertRecord::class);

        $this->csv->addValidator(new ColumnConsistency(3), 'consistency');
        $this->csv->insertOne(['john', 'doe', 'john.doe@example.com']);
        $this->csv->insertOne(['jane', 'jane.doe@example.com']);
    }

    public function testColumnsCountTriggersException(): void
    {
        $this->expectException(InvalidArgument::class);

        new ColumnConsistency(-2);
    }
}
