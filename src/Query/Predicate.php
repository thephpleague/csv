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


/**
 * Enable filtering a record based on its value and/or its offset.
 *
 * The class can be used directly with PHP's
 * <ol>
 * <li>array_filter with the ARRAY_FILTER_USE_BOTH flag.</li>
 * <li>CallbackFilterIterator class.</li>
 * </ol>
 */
interface Predicate
{
    public function __invoke(mixed $value, string|int $key): bool;
}
