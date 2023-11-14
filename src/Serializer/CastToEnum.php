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

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;
use UnitEnum;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    /** @var class-string */
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly BackedEnum|UnitEnum|null $default;

    /**
     * @param ?class-string $enum
     *
     * @throws MappingFailed
     */
    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        ?string $default = null,
        ?string $enum = null,
    ) {
        [$type, $reflection, $this->isNullable] = $this->init($reflectionProperty);
        /** @var class-string $class */
        $class = $reflection->getName();
        if (Type::Mixed->equals($type)) {
            if (null === $enum || !enum_exists($enum)) {
                throw new MappingFailed('`'.$reflectionProperty->getName().'` type is `mixed`; you must specify the Enum class via the `$enum` argument.');
            }
            $class = $enum;
        }

        $this->class = $class;

        try {
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (TypeCastingFailed $exception) {
            throw new MappingFailed(message:'The configuration option for `'.self::class.'` are invalid.', previous: $exception);
        }
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): BackedEnum|UnitEnum|null
    {
        return match (true) {
            null !== $value => $this->cast($value),
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
        };
    }

    /**
     * @throws TypeCastingFailed
     */
    private function cast(string $value): BackedEnum|UnitEnum
    {
        try {
            $enum = new ReflectionEnum($this->class);
            if (!$enum->isBacked()) {
                return $enum->getCase($value)->getValue();
            }

            $backedValue = 'int' === $enum->getBackingType()?->getName() ? filter_var($value, Type::Int->filterFlag()) : $value;

            return $this->class::from($backedValue);
        } catch (Throwable $exception) {
            throw new TypeCastingFailed(message: 'Unable to cast to `'.$this->class.'` the value `'.$value.'`.', previous: $exception);
        }
    }

    /**
     * @return array{0:Type, 1:ReflectionNamedType, 2:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        $type = null;
        $isNullable = false;
        foreach (Type::list($reflectionProperty) as $found) {
            if (!$isNullable && $found[1]->allowsNull()) {
                $isNullable = true;
            }

            if (null === $type && $found[0]->isOneOf(Type::Mixed, Type::Enum)) {
                $type = $found;
            }
        }

        if (null === $type) {
            throw new MappingFailed('`'.$reflectionProperty->getName().'` type is not supported; an Enum or `mixed` is required.');
        }

        return [...$type, $isNullable];
    }
}
