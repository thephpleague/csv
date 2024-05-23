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

namespace League\Csv\Query;

use Iterator;

/**
 * Enable sorting a record based on its value.
 *
 * The class can be used directly with PHP's
 * <ol>
 * <li>usort and uasort.</li>
 * <li>ArrayIterator::uasort.</li>
 * <li>ArrayObject::uasort.</li>
 * </ol>
 */
interface Sort
{
    /**
     * The class comparison method.
     *
     * The method must return an integer less than, equal to, or greater than zero
     * if the first argument is considered to be respectively less than, equal to,
     * or greater than the second.
     */
    public function __invoke(mixed $valueA, mixed $valueB): int;

    /**
     * Sort an iterable structure with the class comparison method and maintain index association.
     */
    public function sort(iterable $value): Iterator;
}
