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

use Closure;
use Countable;
use Deprecated;
use Iterator;
use IteratorAggregate;

/**
 * Represents a Tabular data.
 *
 * @template TValue of array
 * @template-extends IteratorAggregate<array-key, TValue>
 *
 * @method Iterator fetchColumnByName(string $name) returns a column from its name
 * @method Iterator fetchColumnByOffset(int $offset) returns a column from its offset
 * @method mixed value(int|string $column = 0) returns a given value from the first element of the tabular data.
 * @method bool each(Closure $callback) iterates over each record and passes it to a closure. Iteration is interrupted if the closure returns false
 * @method bool exists(Closure $callback) tells whether at least one record satisfies the predicate.
 * @method mixed reduce(Closure $callback, mixed $initial = null) reduces the collection to a single value, passing the result of each iteration into the subsequent iteration
 * @method Iterator getObjects(string $className, array $header = []) Returns the tabular data records as an iterator object containing instance of the defined class name.
 * @method TabularDataReader filter(Query\Predicate|Closure $predicate) returns all the elements of this collection for which your callback function returns `true`
 * @method TabularDataReader slice(int $offset, ?int $length = null) extracts a slice of $length elements starting at position $offset from the Collection.
 * @method TabularDataReader sorted(Query\Sort|Closure $orderBy) sorts the Collection according to the closure provided see Statement::orderBy method
 * @method TabularDataReader select(string|int ...$columnOffsetOrName) extract a selection of the tabular data records columns.
 * @method TabularDataReader selectAllExcept(string|int ...$columnOffsetOrName) specifies the names or index of one or more columns to exclude from the selection of the tabular data records columns.
 * @method TabularDataReader matchingFirstOrFail(string $expression) extract the first found fragment identifier of the tabular data or fail
 * @method TabularDataReader|null matchingFirst(string $expression) extract the first found fragment identifier of the tabular data or return null if none is found
 * @method iterable<int, TabularDataReader> matching(string $expression) extract all found fragment identifiers for the tabular data
 * @method iterable<TabularDataReader> chunkBy(int $recordsCount) Chunk the TabulaDataReader into smaller TabularDataReader instances of the given size or less.
 * @method TabularDataReader mapHeader(array $headers) Returns a new TabulaDataReader with a new set of headers.
 */
interface TabularDataReader extends TabularData, IteratorAggregate, Countable
{
    /**
     * Returns the tabular data rows as an iterator object containing flat array.
     *
     * Each row is represented as a simple array containing values.
     *
     * If the tabular data has a header included as a separate row then each record
     * is combined to the header record and the header record is removed from the iteration.
     *
     * If the tabular data is inconsistent. Missing fields are filled with null values
     * while extra record fields are strip from the returned array.
     *
     * @return Iterator<array-key, TValue>
     */
    public function getIterator(): Iterator;

    /**
     * Returns the number of records contained in the tabular data structure
     * excluding the header record.
     */
    public function count(): int;

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
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.9.0
     *
     * Returns the nth record from the tabular data.
     *
     * By default, if no index is provided the first record of the tabular data is returned
     *
     * @param int $nth_record the tabular data record offset
     *
     * @throws UnableToProcessCsv if argument is less than 0
     */
    #[Deprecated(message:'use League\Csv\TabularDataReader::nth() instead', since:'league/csv:9.9.0')]
    public function fetchOne(int $nth_record = 0): array;
}
