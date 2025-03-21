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

namespace League\Csv\Serializer;

use RuntimeException;
use Throwable;

final class TypeCastingFailed extends RuntimeException implements SerializationFailed
{
    public static function dueToNotNullableType(string $type, ?Throwable $exception = null, ?string $variableName = null): self
    {
        $message = match ($variableName) {
            null => 'The `null` value can not be cast to an `'.$type.'`; the property type is not nullable.',
            default => 'The `null` value can not be cast to an `'.$type.'` for the object property `'.$variableName.'``; the property type is not nullable.',
        };

        return new self($message, 0, $exception);
    }

    public static function dueToInvalidValue(mixed $value, string $type, ?Throwable $previous = null, ?string $variableName = null): self
    {
        if (!is_scalar($value)) {
            $value = gettype($value);
        }

        $message = match ($variableName) {
            null => 'Unable to cast the given data `'.$value.'` to a `'.$type.'`.',
            default => 'Unable to cast the given data `'.$value.'` to a `'.$type.'` for the object property `'.$variableName.'`.',
        };

        return new self($message, 0, $previous);
    }

    public static function dueToUndefinedValue(string|int $offset, ?string $variableName = null): self
    {
        $message = match ($variableName) {
            null => 'Unable to cast the record value; Missing value was for offset`'.$offset.'.',
            default => 'Unable to cast the record value; Missing value was for offset`'.$offset.' and for the object property `'.$variableName.'`.',
        };

        return new self($message, 0);
    }
}
