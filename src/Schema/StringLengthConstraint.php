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

final readonly class StringLengthConstraint implements StringConstraint
{
    /**
     * @param ?positive-int $min
     * @param ?positive-int $max
     */
    private function __construct(
        public ?int $min,
        public ?int $max,
    ) {
        null === $min || $min > 0 || throw new ValueError('Min length must be greater than 0');
        null === $max || $max > 0 || throw new ValueError('Max length must be greater than 0');
        if (null !== $min && null !== $max && $min > $max) {
            throw new ValueError('Minimum length can not be greater than maximum length.');
        }
    }

    /**
     * @param positive-int $length
     */
    public static function min(int $length): self
    {
        return new self(min: $length, max: null);
    }

    /**
     * @param positive-int $length
     */
    public static function max(int $length): self
    {
        return new self(min: null, max: $length);
    }

    /**
     * @param positive-int $length
     */
    public static function fixed(int $length): self
    {
        return new self(min: $length, max: $length);
    }

    /**
     * @param positive-int $min
     * @param positive-int $max
     */
    public static function between(int $min, int $max): self
    {
        return new self(min: $min, max: $max);
    }

    public function apply(string $value): ?string
    {
        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $length = mb_strlen($value);
        if (null !== $this->min && $length < $this->min) {
            return null;
        }

        if (null !== $this->max && $length > $this->max) {
            return null;
        }

        return $value;
    }

    public function fieldTypeName(): string
    {
        $range = (null === $this->min && null === $this->max)
            ? '' :
            (
                $this->min === $this->max
                ? '['.$this->min.']'
                : '['.$this->min.','.$this->max.']'
            );

        return FieldType::String->value.$range;
    }

    public function constraint(): FieldMetadata
    {
        return new FieldMetadata([
            'min_length' => $this->min,
            'max_length' => $this->max,
        ]);
    }
}
