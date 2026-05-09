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

use function filter_var;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function trim;

use const FILTER_VALIDATE_INT;

final class NumericField extends FieldEvaluator implements Field
{
    public function __construct(
        public readonly int|float|null $min = null,
        public readonly int|float|null $max = null,
        float $confidenceThreshold = 0.8
    ) {
        if (null !== $min && null !== $max && $min > $max) {
            throw new \ValueError('Minimum length can not be greater than maximum length.');
        }

        parent::__construct($confidenceThreshold);
    }

    public static function min(int $value, float $confidenceThreshold = 0.8): self
    {
        return new self(min: $value, max: null, confidenceThreshold: $confidenceThreshold);
    }

    public static function max(int $value, float $confidenceThreshold = 0.8): self
    {
        return new self(min: null, max: $value, confidenceThreshold: $confidenceThreshold);
    }

    public static function fixed(int $value, float $confidenceThreshold = 0.8): self
    {
        return new self(min: $value, max: $value, confidenceThreshold: $confidenceThreshold);
    }

    public static function between(int $min, int $max, float $confidenceThreshold = 0.8): self
    {
        return new self(min: $min, max: $max, confidenceThreshold: $confidenceThreshold);
    }

    public static function positive(float $confidenceThreshold = 0.8): self
    {
        return new self(min: 0, confidenceThreshold: $confidenceThreshold);
    }

    public static function negative(float $confidenceThreshold = 0.8): self
    {
        return new self(max: 0, confidenceThreshold: $confidenceThreshold);
    }

    public function type(): FieldType
    {
        return FieldType::Numeric;
    }

    public function name(): string
    {
        $range = (null === $this->min && null === $this->max)
            ? '' :
            (
                $this->min === $this->max
                ? '['.$this->min.']'
                : '['.$this->min.','.$this->max.']'
            );

        return FieldType::Numeric->value.$range;
    }

    public function parse(mixed $value): int|float|null
    {
        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value || !is_numeric($value)) {
                return null;
            }

            $filterValue = filter_var($value, FILTER_VALIDATE_INT);
            $value = false === $filterValue ? (float) $value : $filterValue;
        }

        if (!is_float($value) && !is_int($value)) {
            return null;
        }

        if (null !== $this->min && $value < $this->min) {
            return null;
        }

        if (null !== $this->max && $value > $this->max) {
            return null;
        }

        return $value;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'constraints' => [
                'min_value' => $this->min,
                'max_value' => $this->max,
            ],
        ]);
    }
}
