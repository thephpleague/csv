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

namespace League\Csv\Schema;

use ValueError;

use function is_string;
use function trim;

abstract class FieldEvaluator
{
    protected readonly float $confidenceThreshold;

    public function __construct(float $confidenceThreshold = 0.8)
    {
        $this->confidenceThreshold = self::filterConfidenceThreshold($confidenceThreshold);
    }

    public function confidenceThreshold(): float
    {
        return $this->confidenceThreshold;
    }

    final protected static function filterConfidenceThreshold(float $confidenceThreshold): float
    {
        ($confidenceThreshold >= 0 && $confidenceThreshold <= 1) || throw new ValueError('the confidence threshold must be between 0 and 1.');

        return $confidenceThreshold;
    }

    public function score(iterable $values): float
    {
        $valid = 0;
        $counted = 0;

        foreach ($values as $value) {
            $eval = $this->evaluate($value);
            if (0 === $eval) {
                continue;
            }

            $counted++;
            if (1 === $eval) {
                $valid++;
            }
        }

        return 0 < $counted ? $valid / $counted : 0.0;
    }

    /**
     * @return int<-1, 1>
     */
    public function evaluate(mixed $value): int
    {
        if (null === $value) {
            return 0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return 0;
            }
        }

        return null !== $this->parse($value) ? 1 : -1;
    }

    abstract public function parse(mixed $value): mixed;
}
