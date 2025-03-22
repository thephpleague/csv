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
 * @implements TypeCasting<?bool>
 */
final class CastToBool implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Type $type;
    private readonly TypeCastingInfo $info;
    private ?bool $default = null;

    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        [$this->type, $this->isNullable] = $this->init($reflectionProperty);
        $this->info = TypeCastingInfo::fromAccessor($reflectionProperty);
    }

    public function setOptions(
        ?bool $default = null,
        bool $emptyStringAsNull = false,
    ): void {
        $this->default = $default;
    }

    public function info(): TypeCastingInfo
    {
        return $this->info;
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(mixed $value): ?bool
    {
        $returnValue = match (true) {
            is_bool($value) => $value,
            null !== $value => filter_var($value, Type::Bool->filterFlag()),
            $this->isNullable => $this->default,
            default => throw TypeCastingFailed::dueToNotNullableType('boolean', info: $this->info),
        };

        return match (true) {
            Type::True->equals($this->type) && true !== $returnValue && !$this->isNullable,
            Type::False->equals($this->type) && false !== $returnValue && !$this->isNullable => throw TypeCastingFailed::dueToInvalidValue(match (true) {
                null === $value => 'null',
                '' === $value => 'empty string',
                default => $value,
            }, $this->type->value, info: $this->info),
            default => $returnValue,
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

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Bool, Type::True, Type::False)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'bool', 'mixed');
        }

        return [$type[0], $isNullable];
    }
}
