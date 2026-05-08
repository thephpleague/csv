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

use UnitEnum;
use ValueError;

use function explode;
use function is_string;
use function trim;

use const PHP_INT_MAX;

final class SetField extends FieldEvaluator implements Field
{
    /** @var non-empty-string */
    public readonly string $separator;
    public readonly int $limit;
    public readonly EnumField $enumField;

    /**
     * @param non-empty-string $separator
     */
    public function __construct(EnumField $enumField, string $separator = ',', int $limit = PHP_INT_MAX)
    {
        $separator = trim($separator);
        '' !== $separator || throw new ValueError('The set field separator can not be an empty string.');

        parent::__construct($enumField->confidenceThreshold());
        $this->enumField = $enumField;
        $this->separator = $separator;
        $this->limit = $limit;
    }

    /**
     * @param class-string<UnitEnum> $enumClass
     * @param non-empty-string $separator
     */
    public static function fromEnum(
        string $enumClass,
        string $separator = ',',
        int $limit = PHP_INT_MAX,
        float $confidenceThreshold = 0.8
    ): self {
        return new self(
            new EnumField($enumClass, $confidenceThreshold),
            $separator,
            $limit
        );
    }

    public function type(): FieldType
    {
        return FieldType::Set;
    }

    public function name(): string
    {
        return FieldType::Set->value;
    }

    /**
     * @return list<UnitEnum>|null
     */
    public function parse(mixed $value): mixed
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $result = [];
        foreach (explode($this->separator, $value) as $part) {
            $part = trim($part);
            if ('' === $part || isset($result[$part])) {
                continue;
            }

            $parsed = $this->enumField->parse($part);
            if (null === $parsed) {
                continue;
            }

            $result[$part] = $parsed;
        }

        return array_values($result);
    }

    public function metadata(): FieldMetadata
    {
        return (new FieldMetadata([
            'separator' => $this->separator,
            'limit' => $this->limit,
            'enum' => $this->enumField->metadata(),
        ]));
    }
}
