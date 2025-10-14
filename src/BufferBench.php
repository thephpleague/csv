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

use PhpBench\Attributes as Bench;
use SplFileObject;

use function assert;
use function dirname;

final class BufferBench
{
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4700000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchLoadingRecordsUsingFromSplFileObject(): void
    {
        $path = dirname(__DIR__).'/test_files/prenoms.csv';

        Buffer::from(Reader::from(new SplFileObject($path)));
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4700000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchLoadingRecordsUsingFromStreamResource(): void
    {
        $path = dirname(__DIR__).'/test_files/prenoms.csv';

        Buffer::from(Reader::from($path));
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 40000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchWritingAndDeletingEntries(): void
    {
        $numRows = 10_000;
        $buffer = new Buffer(header: ['foo', 'bar', 'baz']);
        for ($i = 1; $i <= $numRows; ++$i) {
            $buffer->insert(
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
                ["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"],
            );
        }

        $buffer->delete(fn (array $row, int $offset) => ($offset % 2) === 0);
        assert(50_000 === $buffer->recordCount());
    }

}
