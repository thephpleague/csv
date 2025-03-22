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
 * @implements TypeCasting<?float>
 */
final class CastToFloat implements TypeCasting
{
    private readonly bool $isNullable;
    private ?float $default = null;
    private readonly TypeCastingInfo $info;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        $this->isNullable = $this->init($reflectionProperty);
        $this->info = TypeCastingInfo::fromAccessor($reflectionProperty);
    }

    public function info(): TypeCastingInfo
    {
        return $this->info;
    }

    public function setOptions(
        int|float|null $default = null,
        bool $emptyStringAsNull = false,
    ): void {
        $this->default = $default;
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(mixed $value): ?float
    {
        if (null === $value) {
            return match ($this->isNullable) {
                true => $this->default,
                false => throw TypeCastingFailed::dueToNotNullableType('float', info: $this->info),
            };
        }

        is_scalar($value) || throw TypeCastingFailed::dueToInvalidValue($value, Type::Int->value, info: $this->info);

        $float = filter_var($value, Type::Float->filterFlag());

        return match ($float) {
            false => throw TypeCastingFailed::dueToInvalidValue($value, Type::Float->value, info: $this->info),
            default => $float,
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

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Float)) {
                $type = $found;
            }
        }

        null !== $type || throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'float', 'null', 'mixed');

        return $isNullable;
    }
}
