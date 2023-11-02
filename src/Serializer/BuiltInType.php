<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv\Serializer;

use ReflectionNamedType;

use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

enum BuiltInType: string
{
    case Boolean = 'bool';
    case Int =  'int';
    case Float = 'float';
    case String = 'string';
    case True = 'true';
    case False = 'false';
    case Null = 'null';
    case Mixed = 'mixed';

    public static function supports(ReflectionNamedType|string $type): bool
    {
        if ($type instanceof ReflectionNamedType) {
            $type = $type->getName();
        }

        return null !== self::tryFrom(ltrim($type, '?'));
    }

    /**
     * @throws TypeCastingFailed
     */
    public function cast(?string $value): int|float|bool|string|null
    {
        return match ($this) {
            self::Int => $this->castToInt($value),
            self::Float => $this->castToFloat($value),
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOL),
            self::True => $this->castToTrue($value),
            self::False => $this->castToFalse($value),
            self::String => $this->castToString($value),
            self::Null => $this->castToNull($value),
            self::Mixed => $value,
        };
    }

    private function castToTrue(?string $value): bool
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_BOOL);

        return match (true) {
            $returnedValue => true,
            default => throw new TypeCastingFailed('The value `'.$value.'` can not be cast to the boolean true.'),
        };
    }

    private function castToFalse(?string $value): bool
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_BOOL);

        return match (false) {
            $returnedValue => false,
            default => throw new TypeCastingFailed('The value `'.$value.'` can not be cast to the boolean false.'),
        };
    }

    /** @return null */
    private function castToNull(?string $value)
    {
        return match ($value) {
            null => $value,
            default => throw new TypeCastingFailed('The value `'.$value.'` can not be cast to an integer.'),
        };
    }

    private function castToString(?string $value): string
    {
        return match (null) {
            $value => throw new TypeCastingFailed('The `null` value can not be cast to a string.'),
            default => $value,
        };
    }

    private function castToInt(?string $value): int
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_INT);

        return match (false) {
            $returnedValue => throw new TypeCastingFailed('The value `'.$value.'` can not be cast to an integer.'),
            default => $returnedValue,
        };
    }

    private function castToFloat(?string $value): float
    {
        $returnedValue = filter_var($value, FILTER_VALIDATE_FLOAT);

        return match (false) {
            $returnedValue => throw new TypeCastingFailed('The value `'.$value.'` can not be cast to a float.'),
            default => $returnedValue,
        };
    }
}
