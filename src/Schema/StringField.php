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

use function is_string;
use function trim;

final class StringField extends FieldEvaluator implements Field
{
    public function __construct(
        public readonly ?StringConstraint $constraint = null,
        float $confidenceThreshold = 0.0
    ) {
        parent::__construct($confidenceThreshold);
    }

    /**
     * @param positive-int $length
     */
    public static function max(int $length, float $confidenceThreshold = 0.8): self
    {
        return new self(StringLengthConstraint::max($length), $confidenceThreshold);
    }

    /**
     * @param positive-int $length
     */
    public static function min(int $length, float $confidenceThreshold = 0.8): self
    {
        return new self(StringLengthConstraint::min($length), $confidenceThreshold);
    }

    /**
     * @param positive-int $length
     */
    public static function fixed(int $length, float $confidenceThreshold = 0.8): self
    {
        return new self(StringLengthConstraint::fixed($length), $confidenceThreshold);
    }

    public static function uuid(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::uuid(), $confidenceThreshold);
    }

    public static function ulid(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::ulid(), $confidenceThreshold);
    }

    public static function hexColor(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::hexColor(), $confidenceThreshold);
    }

    public static function jwtToken(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::jwtToken(), $confidenceThreshold);
    }

    public static function md5(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::md5(), $confidenceThreshold);
    }

    public static function sha1(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringConstraint::sha1(), $confidenceThreshold);
    }

    public static function cases(float $confidenceThreshold = 0.8): FieldList
    {
        return new FieldList(
            self::uuid($confidenceThreshold),
            self::ulid($confidenceThreshold),
            self::hexColor($confidenceThreshold),
            self::jwtToken($confidenceThreshold),
            self::md5($confidenceThreshold),
            self::sha1($confidenceThreshold),
        );
    }

    public function type(): FieldType
    {
        return FieldType::String;
    }

    public function name(): string
    {
        return $this->constraint?->fieldTypeName() ?? FieldType::String->value;
    }

    public function parse(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return match (true) {
            '' === $value => null,
            null === $this->constraint => $value,
            default => $this->constraint->apply($value),
        };
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }

    /**
     * @return int<-1, 1>
     */
    public function evaluate(mixed $value): int
    {
        return match (true) {
            null !== $this->constraint => parent::evaluate($value),
            is_string($value) => 1,
            default => 0,
        };
    }

    public function score(iterable $values): float
    {
        return null === $this->constraint ? 1.0 : parent::score($values);
    }

    public function confidenceThreshold(): float
    {
        return null === $this->constraint ? 0.0 : parent::confidenceThreshold();
    }
}
