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

use Generator;
use mysqli_result;
use PDO;
use PDOStatement;
use PgSql\Result;
use RuntimeException;
use SQLite3Result;

use function array_column;
use function array_map;
use function pg_fetch_assoc;
use function pg_field_name;
use function pg_num_fields;
use function range;

use const SQLITE3_ASSOC;

final class RdbmsResult
{
    /**
     * @throws RuntimeException If no column names information is found.
     *
     * @return list<string>
     */
    public static function columnNames(PDOStatement|Result|mysqli_result|SQLite3Result $result): array
    {
        return match (true) {
            $result instanceof PDOStatement => array_map(fn (int $index): string => $result->getColumnMeta($index)['name'] ?? throw new RuntimeException('Unable to get metadata for column '.$index), range(0, $result->columnCount() - 1)),
            $result instanceof mysqli_result => array_column($result->fetch_fields(), 'name'),
            $result instanceof Result => array_map(fn (int $index) => pg_field_name($result, $index), range(0, pg_num_fields($result) - 1)),
            $result instanceof SQLite3Result => array_map($result->columnName(...), range(0, $result->numColumns() - 1)),
        };
    }

    /**
     * @return Generator<int, array<array-key, mixed>>
     */
    public static function rows(PDOStatement|Result|mysqli_result|SQLite3Result $result): Generator
    {
        if ($result instanceof PDOStatement) {
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                yield $row; /* @phpstan-ignore-line */
            }

            return;
        }

        if ($result instanceof Result) {
            while ($row = pg_fetch_assoc($result)) {
                yield $row;
            }

            return;
        }

        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                yield $row;
            }

            return;
        }

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            yield $row;
        }
    }
}
