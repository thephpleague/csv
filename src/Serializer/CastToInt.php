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

use ReflectionParameter;
use ReflectionProperty;

use function filter_var;

/**
 * @implements TypeCasting<?int>
 */
final class CastToInt implements TypeCasting
{
    private readonly bool $isNullable;
    private ?int $default = null;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        $this->isNullable = $this->init($reflectionProperty);
    }

    public function setOptions(?int $default = null): void
    {
        $this->default = $default;
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?int
    {
        if (null === $value) {
            return match ($this->isNullable) {
                true => $this->default,
                false => throw TypeCastingFailed::dueToNotNullableType('integer'),
            };
        }

        $int = filter_var($value, Type::Int->filterFlag());

        return match ($int) {
            false => throw TypeCastingFailed::dueToInvalidValue($value, Type::Int->value),
            default => $int,
        };
    }

    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): bool
    {
        if (null === $reflectionProperty->getType()) {
            return true;
        }

        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Int, Type::Float)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'int', 'float', 'null', 'mixed');
        }

        return $isNullable;
    }
}
