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

namespace League\Csv\Query\Constraint;

use ArrayIterator;
use CallbackFilterIterator;
use Closure;
use Iterator;
use IteratorIterator;
use League\Csv\Query\Predicate;
use League\Csv\Query\PredicateCombinator;

use Traversable;
use function array_reduce;

/**
 * @phpstan-import-type Condition from PredicateCombinator
 * @phpstan-import-type ConditionExtended from PredicateCombinator
 */
final class Criteria implements PredicateCombinator
{
    /**
     * @param Condition $predicate
     */
    private function __construct(private readonly Predicate|Closure $predicate)
    {
    }

    /**
     * Creates a new instance with predicates join using the logical AND operator.
     *
     * @param ConditionExtended ...$predicates
     */
    public static function all(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (mixed $value, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if (!$predicate($value, $key)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Creates a new instance with predicates join using the logical NOT operator.
     *
     * @param ConditionExtended ...$predicates
     */
    public static function none(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (mixed $value, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if ($predicate($value, $key)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Creates a new instance with predicates join using the logical OR operator.
     *
     * @param ConditionExtended ...$predicates
     */
    public static function any(Predicate|Closure|callable ...$predicates): self
    {
        return new self(function (mixed $value, int|string $key) use ($predicates): bool {
            foreach ($predicates as $predicate) {
                if ($predicate($value, $key)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Creates a new instance with predicates join using the logical XOR operator.
     *
     * @param ConditionExtended ...$predicates
     */
    public static function xany(Predicate|Closure|callable ...$predicates): self
    {
        return new self(fn (mixed $value, int|string $key): bool => array_reduce(
            $predicates,
            fn (bool $bool, Predicate|Closure|callable $predicate) => $predicate($value, $key) xor $bool,
            false
        ));
    }

    public function __invoke(mixed $value, int|string $key): bool
    {
        return ($this->predicate)($value, $key);
    }

    public function filter(iterable $value): Iterator
    {
        return new CallbackFilterIterator(match (true) {
            $value instanceof Iterator => $value,
            $value instanceof Traversable => new IteratorIterator($value),
            default => new ArrayIterator($value),
        }, $this);
    }

    /**
     * @param ConditionExtended ...$predicates
     */
    public function and(Predicate|Closure|callable ...$predicates): self
    {
        return self::all($this->predicate, ...$predicates);
    }

    /**
     * @param ConditionExtended ...$predicates
     */
    public function not(Predicate|Closure|callable ...$predicates): self
    {
        return self::none($this->predicate, ...$predicates);
    }

    /**
     * @param ConditionExtended ...$predicates
     */
    public function or(Predicate|Closure|callable ...$predicates): self
    {
        return self::any($this->predicate, ...$predicates);
    }

    /**
     * @param ConditionExtended ...$predicates
     */
    public function xor(Predicate|Closure|callable ...$predicates): self
    {
        return self::xany($this->predicate, ...$predicates);
    }
}
