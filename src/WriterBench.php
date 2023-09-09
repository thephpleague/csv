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

final class WriterBench
{
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchWriting1MRowsCSVUsingFileObject(): void
    {
        $numRows = 1_000_000;
        $path = dirname(__DIR__).'/test_files/csv_write_with_one_million_rows.csv';

        $writer = Writer::createFromFileObject(new SplFileObject($path, 'w'));
        for ($i = 1; $i <= $numRows; ++$i) {
            $writer->insertOne(["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"]);
        }

        assert($numRows === $this->getNumWrittenRows($path));
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 2097152'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchWriting1MRowsCSVUsingStream(): void
    {
        $numRows = 1_000_000;
        $path = dirname(__DIR__).'/test_files/csv_write_with_one_million_rows.csv';

        $writer = Writer::createFromPath($path, 'w');
        for ($i = 1; $i <= $numRows; ++$i) {
            $writer->insertOne(["csv--{$i}1", "csv--{$i}2", "csv--{$i}3"]);
        }

        assert($numRows === $this->getNumWrittenRows($path));
    }

    private function getNumWrittenRows(string $resourcePath): int
    {
        $lineCountResult = shell_exec("wc -l {$resourcePath}");
        assert(false !== $lineCountResult);

        return (int) $lineCountResult;
    }
}
