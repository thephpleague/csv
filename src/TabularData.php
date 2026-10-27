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
use Iterator;
use League\Csv\Schema\Inspector;
use League\Csv\Schema\Schema;

/**
 * @template T of object
 * @method array nth(int $nth) Returns the nth record from the tabular data.
 * @method array nthInferred(int $nth, ?Inspector $inspector = null, array $header = []) Returns the nth record as a typed array.
 * @method T|null nthAsObject(int $nth, class-string<T> $className, array $header = []) Returns the nth record as an instance of the specified class, or null if no record exists.
 * @method array first() Returns the first record from the tabular data.
 * @method array firstInferred(?Inspector $inspector = null, array $header = []) Returns the first record as a typed array.
 * @method T|null firstAsObject(class-string<T> $className, array $header = []) Returns the first record as an instance of the specified class, or null if no record exists.
 * @method array last() Returns the last record from the tabular data.
 * @method array lastInferred(?Inspector $inspector = null, array $header = []) Returns the last record as a typed array.
 * @method T|null lastAsObject(class-string<T> $className, array $header = []) Returns the last record as an instance of the specified class, or null if no record exists.
 * @method mixed value(int|string $column = 0) Returns a value from the first record of the tabular data.
 * @method mixed valueInferred(int|string $column = 0, ?Inspector $inspector = null, array $header = []) Returns a type-inferred value from the first record, or null if its type cannot be inferred.
 * @method Iterator<array> inferRecords(?Inspector $inspector = null, array $header = []) Returns an iterator over the tabular data records as typed arrays.
 * @method Iterator<T> getRecordsAsObject(class-string<T> $className, array $header = []) Returns an iterator over the tabular data records converted to instances of the specified class.
 * @method Schema inferSchema(?Inspector $inspector = null, array $header = []) Returns the inferred schema of the tabular data.
 * @method Iterator map(callable $callback) Run a map over each container record.
 * @method mixed reduce(Closure $callback, mixed $initial = null) reduces the collection to a single value, passing the result of each iteration into the subsequent iteration.
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
     * @param array<int, string> $header an optional header mapper to use instead of the tabular data header
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
}
