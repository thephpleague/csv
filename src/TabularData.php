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
use IteratorAggregate;

/**
 * @template TValue of array
 * @template-extends IteratorAggregate<array-key, TValue>
 */
interface TabularData extends IteratorAggregate
{
    /**
     * Returns the header associated with the tabular data.
     *
     * The header must contain unique string or to be an empty array
     * if no header was specified.
     *
     * @return array<string>
     */
    public function getHeader(): array;

    /**
     * Returns the tabular data records as an iterator object containing flat array.
     *
     * Each record is represented as a simple array containing strings or null values.
     *
     * If the CSV document has a header record then each record is combined
     * to the header record and the header record is removed from the iterator.
     *
     * If the CSV document is inconsistent. Missing record fields are
     * filled with null values while extra record fields are strip from
     * the returned object.
     *
     * @return Iterator<array-key, array<array-key, mixed>>
     */
    public function getIterator(): Iterator;
}