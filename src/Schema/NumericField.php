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
    public function type(): FieldType
    {
        return FieldType::Numeric;
    }

    public function name(): string
    {
        return FieldType::Numeric->value;
    }

    public function parse(mixed $value): int|float|null
    {
        if (is_float($value) || is_int($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value || !is_numeric($value)) {
            return null;
        }

        $filterValue = filter_var($value, FILTER_VALIDATE_INT);

        return false === $filterValue ? (float) $value : $filterValue;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }
}
