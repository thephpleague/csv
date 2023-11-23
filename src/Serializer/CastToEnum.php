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
use ReflectionParameter;
use ReflectionProperty;
use Throwable;
use UnitEnum;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    /** @var class-string<UnitEnum|BackedEnum> */
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly ?UnitEnum $default;

    /**
     * @param ?class-string<UnitEnum|BackedEnum> $className
     *
     * @throws MappingFailed
     */
    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty,
        ?string $default = null,
        ?string $className = null,
    ) {
        [$type, $class, $this->isNullable] = $this->init($reflectionProperty);
        if (Type::Mixed->equals($type) || in_array($class, [BackedEnum::class , UnitEnum::class], true)) {
            if (null === $className || !enum_exists($className)) {
                throw new MappingFailed('`'.$reflectionProperty->getName().'` type is `'.($class ?? 'mixed').'` but the specified class via the `$className` argument is invalid or could not be found.');
            }

            $class = $className;
        }

        $this->class = $class;

        try {
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (TypeCastingFailed $exception) {
            throw new MappingFailed(message:'The `default` option is invalid.', previous: $exception);
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
            default => throw TypeCastingFailed::dueToNotNullableType($this->class),
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

            return $this->class::from($backedValue); /* @phpstan-ignore-line */
        } catch (Throwable $exception) {
            throw throw TypeCastingFailed::dueToInvalidValue($value, $this->class, $exception);
        }
    }

    /**
     * @return array{0:Type, 1:class-string<UnitEnum|BackedEnum>, 2:bool}
     */
    private function init(ReflectionProperty|ReflectionParameter $reflectionProperty): array
    {
        if (null === $reflectionProperty->getType()) {
            return [Type::Mixed, UnitEnum::class, true];
        }

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
            throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'enum', 'mixed');
        }

        /** @var class-string<UnitEnum|BackedEnum> $className */
        $className = $type[1]->getName();

        return [$type[0], $className, $isNullable];
    }
}
