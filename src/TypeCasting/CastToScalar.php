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

namespace League\Csv\TypeCasting;

use RuntimeException;

use const FILTER_VALIDATE_BOOL;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

/**
 * @implements TypeCasting<int|float|bool|string|null>
 */
final class CastToScalar implements TypeCasting
{
    /**
     * @throws RuntimeException
     */
    public function toVariable(?string $value, string $type): int|float|bool|string|null
    {
        if (in_array($value, ['', null], true) && str_starts_with($type, '?')) {
            return null;
        }

        return match (ltrim($type, '?')) {
            'int' => $this->castToInt($value),
            'float' => $this->castToFloat($value),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'string' => $this->castToString($value),
            'null' => $this->castToNull($value),
            default => throw new RuntimeException('Unable to convert the given data to a PHP scalar variable.'),
        };
    }

    /** @return null */
    private function castToNull(?string $value)
    {
        return match ($value) {
            null => $value,
            default => throw new RuntimeException('The value `'.$value.'` can not be cast to an integer.'),
        };
    }

    private function castToString(?string $value): string
    {
        return match (null) {
            $value => throw new RuntimeException('The `null` value can not be cast to a string.'),
            default => $value,
        };
    }

    private function castToInt(?string $value): int
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_INT);

        return match (false) {
            $returnedValue => throw new RuntimeException('The value `'.$value.'` can not be cast to an integer.'),
            default => $returnedValue,
        };
    }

    private function castToFloat(?string $value): float
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_FLOAT);

        return match (false) {
            $returnedValue => throw new RuntimeException('The value `'.$value.'` can not be cast to a float.'),
            default => $returnedValue,
        };
    }
}
