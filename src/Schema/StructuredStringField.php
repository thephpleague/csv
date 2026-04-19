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
use function preg_match;
use function trim;

final class StructuredStringField extends FieldEvaluator implements Field
{
    public function __construct(public readonly StructuredStringFieldDefinition $definition)
    {
        parent::__construct($definition->confidenceThreshold);
    }

    public static function uuid(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::uuid($confidenceThreshold));
    }

    public static function ulid(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::ulid($confidenceThreshold));
    }

    public static function hexColor(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::hexColor($confidenceThreshold));
    }

    public static function jwtToken(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::jwtToken($confidenceThreshold));
    }

    public static function md5(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::md5($confidenceThreshold));
    }

    public static function sha1(float $confidenceThreshold = 0.8): self
    {
        return new self(StructuredStringFieldDefinition::sha1($confidenceThreshold));
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
        return FieldType::StructuredString;
    }

    public function name(): string
    {
        return $this->definition->fieldTypeName;
    }

    public function parse(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return ('' === $value || 1 !== preg_match($this->definition->pattern, $value)) ? null : $value;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'pattern' => $this->definition->pattern,
        ]);
    }
}
