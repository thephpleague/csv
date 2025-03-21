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
    private readonly string $variableName;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        $this->isNullable = $this->init($reflectionProperty);
        $this->variableName = $reflectionProperty->getName();
    }

    public function variableName(): string
    {
        return $this->variableName;
    }

    public function setOptions(
        ?int $default = null,
        bool $emptyStringAsNull = false,
    ): void {
        $this->default = $default;
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(mixed $value): ?int
    {
        if (null === $value) {
            return match ($this->isNullable) {
                true => $this->default,
                false => throw TypeCastingFailed::dueToNotNullableType('integer', variableName: $this->variableName),
            };
        }

        is_scalar($value) || throw TypeCastingFailed::dueToInvalidValue($value, Type::Int->value, variableName: $this->variableName);

        $int = filter_var($value, Type::Int->filterFlag());

        return match ($int) {
            false => throw TypeCastingFailed::dueToInvalidValue($value, Type::Int->value, variableName: $this->variableName),
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

        null !== $type || throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'int', 'float', 'null', 'mixed');

        return $isNullable;
    }
}
