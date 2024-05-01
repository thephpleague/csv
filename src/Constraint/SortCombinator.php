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

namespace League\Csv\Constraint;

use Closure;

interface SortCombinator extends Sort
{
    /**
     * @param Sort|Closure(array, array): int ...$sorts
     */
    public function append(Sort|Closure ...$sorts): self;

    /**
     * @param Sort|Closure(array, array): int ...$sorts
     */
    public function prepend(Sort|Closure ...$sorts): self;
}
