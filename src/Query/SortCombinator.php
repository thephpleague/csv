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

use Closure;

/**
 * @phpstan-type Ordering Sort|Closure(mixed, mixed): int
 * @phpstan-type OrderingExtended Sort|Closure(mixed, mixed): int|callable(mixed, mixed): int
 */
interface SortCombinator extends Sort
{
    /**
     * Return an instance with the specified sorting algorithm
     * added after the currently registered sorting algorithms.
     *
     * This method MUST retain the state of the current instance,
     * and return an instance that contains the specified changes.
     *
     * @param Ordering ...$sorts
     */
    public function append(Sort|Closure ...$sorts): self;

    /**
     * Return an instance with the specified sorting algorithm
     * added before the currently registered sorting algorithms.
     *
     * This method MUST retain the state of the current instance,
     * and return an instance that contains the specified changes.
     *
     * @param Ordering ...$sorts
     */
    public function prepend(Sort|Closure ...$sorts): self;
}
