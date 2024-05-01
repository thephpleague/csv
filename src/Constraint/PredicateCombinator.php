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

interface PredicateCombinator extends Predicate
{
    /**
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function and(Predicate|Closure ...$predicates): self;

    /**
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function or(Predicate|Closure ...$predicates): self;

    /**
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function not(Predicate|Closure ...$predicates): self;

    /**
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function xor(Predicate|Closure ...$predicates): self;
}
