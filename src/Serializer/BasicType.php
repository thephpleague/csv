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

use DateTimeInterface;

use function class_exists;
use function class_implements;
use function enum_exists;
use function in_array;
use function interface_exists;
use function ltrim;

use const FILTER_UNSAFE_RAW;
use const FILTER_VALIDATE_BOOL;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

enum BasicType: string
{
    case Bool = 'bool';
    case Int =  'int';
    case Float = 'float';
    case String = 'string';
    case Mixed = 'mixed';
    case Array = 'array';
    case Iterable = 'iterable';
    case Enum = 'enum';
    case Date = 'date';

    public function equals(mixed $value): bool
    {
        return $value instanceof self
            && $value === $this;
    }

    public function isOneOf(self ...$types): bool
    {
        return in_array($this, $types, true);
    }

    public static function tryFromPropertyType(string $propertyType): ?self
    {
        $type = ltrim($propertyType, '?');
        $basicType = self::tryFrom($type);

        return match (true) {
            $basicType instanceof self => $basicType,
            enum_exists($type) => self::Enum,
            interface_exists($type) && DateTimeInterface::class === $type,
            class_exists($type) && in_array(DateTimeInterface::class, class_implements($type), true) => self::Date,
            default => null,
        };
    }

    public function filterFlag(): int
    {
        return match ($this) {
            self::Bool => FILTER_VALIDATE_BOOL,
            self::Int => FILTER_VALIDATE_INT,
            self::Float => FILTER_VALIDATE_FLOAT,
            default => FILTER_UNSAFE_RAW,
        };
    }

    public function isScalar(): bool
    {
        return match ($this) {
            self::Bool,
            self::Int,
            self::Float,
            self::String => true,
            default => false,
        };
    }
}
