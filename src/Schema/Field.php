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

interface Field extends FieldParser
{
    public function type(): FieldType;

    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Returns the confidence on the field value.
     *
     * The range of valide value is from 0.0 up to including 1.0
     */
    public function confidenceThreshold(): float;

    /**
     * Scores a sample of values to estimate type confidence.
     *
     * IMPORTANT:
     * The iterable is expected to be a *representative sample* of the dataset,
     * not the full dataset. Implementations may assume limited size for performance.
     *
     * @param iterable $values Sample of dataset values
     */
    public function score(iterable $values): float;

    /**
     * Score a single value to estimate its type.
     *
     * returns -1 if the value is invalid
     * returns 0 if the value is skipped
     * returns 1 if the value is valid
     *
     * @return int<-1, 1>
     */
    public function evaluate(mixed $value): int;

    public function metadata(): FieldMetadata;
}
