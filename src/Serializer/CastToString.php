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

/**
 * @implements TypeCasting<?string>
 */
final class CastToString implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;
    private ?string $default = null;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        [$this->type, $this->isNullable] = $this->init($reflectionProperty);
    }

    public function setOptions(
        ?string $default = null,
        bool $emptyStringAsNull = false,
    ): void {
        $this->default = $default;
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(mixed $value): ?string
    {
        $returnedValue = match (true) {
            is_string($value) => $value,
            $this->isNullable => $this->default,
            default => throw TypeCastingFailed::dueToNotNullableType($this->type->value),
        };

        return match (true) {
            Type::Null->equals($this->type) && null !== $returnedValue => throw TypeCastingFailed::dueToInvalidValue(match (true) {
                null === $value => 'null',
                '' === $value => 'empty string',
                default => $value,
            }, $this->type->value),
            default => $returnedValue,
        };
    }

    /**
     * @return array{0:Type, 1:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        if (null === $reflectionProperty->getType()) {
            return [Type::Mixed, true];
        }

        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::String, Type::Mixed, Type::Null)) {
                $type = $found;
            }
        }

        null !== $type || throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'string', 'mixed', 'null');

        return [$type[0], $isNullable];
    }
}
