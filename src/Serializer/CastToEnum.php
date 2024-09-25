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
    private readonly bool $isNullable;
    private readonly Type $type;
    private ?UnitEnum $default = null;
    private readonly string $propertyName;
    /** @var class-string<UnitEnum|BackedEnum> */
    private string $class;

    /**
     * @throws MappingFailed
     */
    public function __construct(ReflectionProperty|ReflectionParameter $reflectionProperty)
    {
        [$this->type, $this->class, $this->isNullable] = $this->init($reflectionProperty);
        $this->propertyName = $reflectionProperty->getName();
    }

    /**
     * @param ?class-string<UnitEnum|BackedEnum> $className *
     *
     * @throws MappingFailed
     */
    public function setOptions(
        ?string $default = null,
        ?string $className = null,
        bool $emptyStringAsNull = false,
    ): void {
        if (Type::Mixed->equals($this->type) || in_array($this->class, [BackedEnum::class , UnitEnum::class], true)) {
            (null !== $className && enum_exists($className)) || throw new MappingFailed('`'.$this->propertyName.'` type is `'.($this->class ?? 'mixed').'` but the specified class via the `$className` argument is invalid or could not be found.');
            $this->class = $className;
        }

        try {
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (TypeCastingFailed $exception) {
            throw new MappingFailed(message:'The `default` option is invalid.', previous: $exception);
        }
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(mixed $value): BackedEnum|UnitEnum|null
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
    private function cast(mixed $value): BackedEnum|UnitEnum
    {
        if ($value instanceof $this->class) {
            return $value;
        }

        is_string($value) || throw throw TypeCastingFailed::dueToInvalidValue($value, $this->class);

        try {
            $enum = new ReflectionEnum($this->class);
            if (!$enum->isBacked()) {
                return $enum->getCase($value)->getValue();
            }

            $backedValue = 'int' === $enum->getBackingType()->getName() ? filter_var($value, Type::Int->filterFlag()) : $value;

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

        null !== $type || throw throw MappingFailed::dueToTypeCastingUnsupportedType($reflectionProperty, $this, 'enum', 'mixed');

        /** @var class-string<UnitEnum|BackedEnum> $className */
        $className = $type[1]->getName();

        return [$type[0], $className, $isNullable];
    }
}
