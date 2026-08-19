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

use PHPUnit\Framework\Attributes\CoversClass;

use function filter_var;
use function in_array;
use function is_bool;
use function is_string;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

#[CoversClass(BooleanField::class)]
final class BooleanField extends FieldEvaluator implements Field
{
    public function type(): FieldType
    {
        return FieldType::Boolean;
    }

    public function name(): string
    {
        return FieldType::Boolean->value;
    }

    public function parse(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_string($value) && !in_array($value, [0, 1], true)) {
            return null;
        }

        $value = trim((string) $value);
        if ('' === $value) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }
}
