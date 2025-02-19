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

/**
 * @method array nth(int $nth) returns the nth record from the tabular data.
 * @method object|null nthAsObject(int $nth, string $className, array $header = []) returns the nth record from the tabular data as an instance of the defined class name.
 * @method Iterator map(callable $callback) Run a map over each container record.
 * @method Iterator getRecordsAsObject(string $className, array $header = []) Returns the tabular data records as an iterator object containing instance of the defined class name.
 */
interface TabularData
{
    /**
     * Returns the header associated with the tabular data.
     *
     * The header must contain unique string or be an empty array
     * if no header is specified.
     *
     * @return array<string>
     */
    public function getHeader(): array;

    /**
     * Returns the tabular data records as an iterator object.
     *
     * Each record is represented as a simple array containing strings or null values.
     *
     * If the tabular data has a header record then each record is combined
     * to the header record and the header record is removed from the iterator.
     *
     * If the tabular data is inconsistent. Missing record fields are
     * filled with null values while extra record fields are strip from
     * the returned object.
     *
     * @param array<string> $header an optional header mapper to use instead of the CSV document header
     *
     * @return Iterator<array-key, array<array-key, mixed>>
     */
    public function getRecords(array $header = []): Iterator;

    /**
     * Returns a single column from the next record of the tabular data.
     *
     * By default, if no value is supplied the first column is fetched
     *
     * @param string|int $index CSV column index
     *
     * @throws UnableToProcessCsv if the column index is invalid or not found
     *
     * @return Iterator<int, mixed>
     */
    public function fetchColumn(string|int $index = 0): Iterator;

    /**
     * Returns the next key-value pairs from the tabular data (first
     * column is the key, second column is the value).
     *
     * By default, if no column index is provided:
     * - the first column is used to provide the keys
     * - the second column is used to provide the value
     *
     * @param string|int $offset_index The column index to serve as offset
     * @param string|int $value_index The column index to serve as value
     *
     * @throws UnableToProcessCsv if the column index is invalid or not found
     */
    public function fetchPairs(string|int $offset_index = 0, string|int $value_index = 1): Iterator;

    /**
     * Returns the number of records contained in the tabular data structure
     * excluding the header record.
     */
    public function recordCount(): int;
}
