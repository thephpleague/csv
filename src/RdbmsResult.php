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

use Iterator;
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
            $result instanceof mysqli_result => array_column($result->fetch_fields(), 'name'),
            $result instanceof Result => array_map(fn (int $index) => pg_field_name($result, $index), range(0, pg_num_fields($result) - 1)),
            $result instanceof SQLite3Result => array_map($result->columnName(...), range(0, $result->numColumns() - 1)),
            $result instanceof PDOStatement => array_map(fn (int $index): string => $result->getColumnMeta($index)['name'] ?? throw new RuntimeException('Unable to get metadata for column '.$index), range(0, $result->columnCount() - 1)),
        };
    }

    /**
     * @return array<int, array<array-key, mixed>>
     */
    public static function rows(PDOStatement|Result|mysqli_result|SQLite3Result $result): array
    {
        if ($result instanceof PDOStatement) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        }

        /** @var array<int, array<array-key, mixed>> $records */
        $records = [];
        if ($result instanceof mysqli_result) {
            while ($record = $result->fetch_assoc()) {
                $records[] = $record;
            }

            return $records;
        }

        if ($result instanceof Result) {
            while ($record = pg_fetch_assoc($result)) {
                $records[] = $record;
            }

            return $records;
        }

        while ($record = $result->fetchArray(SQLITE3_ASSOC)) {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return Iterator<int, array<array-key, mixed>>
     */
    public static function iteratorRows(PDOStatement|Result|mysqli_result|SQLite3Result $result): Iterator
    {
        return MapIterator::toIterator(self::rows($result));
    }
}
