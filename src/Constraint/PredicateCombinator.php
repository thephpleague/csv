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
     * Return an instance with the specified predicates
     * joined together and with the current predicate
     * using the AND Logical operator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function and(Predicate|Closure ...$predicates): self;

    /**
     * Return an instance with the specified predicates
     * joined together and with the current predicate
     * using the OR Logical operator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function or(Predicate|Closure ...$predicates): self;

    /**
     * Return an instance with the specified predicates
     * joined together and with the current predicate
     * using the NOT Logical operator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function not(Predicate|Closure ...$predicates): self;

    /**
     * Return an instance with the specified predicates
     * joined together and with the current predicate
     * using the XOR Logical operator.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified changes.
     *
     * @param Predicate|Closure(array, array-key): bool ...$predicates
     */
    public function xor(Predicate|Closure ...$predicates): self;
}