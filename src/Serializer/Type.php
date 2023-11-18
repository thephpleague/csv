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
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

use function class_exists;
use function enum_exists;
use function in_array;

use const FILTER_UNSAFE_RAW;
use const FILTER_VALIDATE_BOOL;
use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

enum Type: string
{
    case Bool = 'bool';
    case True = 'true';
    case False = 'false';
    case Null = 'null';
    case Int = 'int';
    case Float = 'float';
    case String = 'string';
    case Mixed = 'mixed';
    case Array = 'array';
    case Iterable = 'iterable';
    case Enum = UnitEnum::class;
    case Date = DateTimeInterface::class;

    public function equals(mixed $value): bool
    {
        return $value instanceof self
            && $value === $this;
    }

    public function isOneOf(self ...$types): bool
    {
        return in_array($this, $types, true);
    }

    public function isBuiltIn(): bool
    {
        return match ($this) {
            self::Date, self::Enum => false,
            default => true,
        };
    }

    public function filterFlag(): int
    {
        return match ($this) {
            self::Bool,
            self::True,
            self::False => FILTER_VALIDATE_BOOL,
            self::Int => FILTER_VALIDATE_INT,
            self::Float => FILTER_VALIDATE_FLOAT,
            default => FILTER_UNSAFE_RAW,
        };
    }

    public function isScalar(): bool
    {
        return match ($this) {
            self::Bool,
            self::True,
            self::False,
            self::Int,
            self::Float,
            self::String => true,
            default => false,
        };
    }

    /**
     * @return list<array{0:Type, 1: ReflectionNamedType}>
     */
    public static function list(ReflectionParameter|ReflectionProperty $reflectionProperty): array
    {
        $reflectionType = $reflectionProperty->getType() ?? throw MappingFailed::dueToUnsupportedType($reflectionProperty);

        $foundTypes = static function (array $res, ReflectionType $reflectionType) {
            if (!$reflectionType instanceof ReflectionNamedType) {
                return $res;
            }

            $type = self::tryFromName($reflectionType->getName());
            if (null !== $type) {
                $res[] = [$type, $reflectionType];
            }

            return $res;
        };

        return match (true) {
            $reflectionType instanceof ReflectionNamedType => $foundTypes([], $reflectionType),
            $reflectionType instanceof ReflectionUnionType => array_reduce($reflectionType->getTypes(), $foundTypes, []),
            default => [],
        };
    }

    public static function tryFromReflectionType(ReflectionType $type): ?self
    {
        if ($type instanceof ReflectionNamedType) {
            return self::tryFromName($type->getName());
        }

        if (!$type instanceof ReflectionUnionType) {
            return null;
        }

        foreach ($type->getTypes() as $innerType) {
            if (!$innerType instanceof ReflectionNamedType) {
                continue;
            }

            $result = self::tryFromName($innerType->getName());
            if ($result instanceof self) {
                return $result;
            }
        }

        return null;
    }

    private static function tryFromName(string $propertyType): ?self
    {
        $type = self::tryFrom($propertyType);

        return match (true) {
            $type instanceof self => $type,
            enum_exists($propertyType) => self::Enum,
            class_exists($propertyType) && (new ReflectionClass($propertyType))->implementsInterface(DateTimeInterface::class) => self::Date,
            default => null,
        };
    }
}
