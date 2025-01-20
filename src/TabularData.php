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
     * The header must contain unique string or be an empty array
     * if no header is specified.
     *
     * @return array<string>
     */
    public function getHeader(): array;

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
}
