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
use ReflectionException;
use Throwable;
use UnitEnum;

use const FILTER_VALIDATE_INT;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly BackedEnum|UnitEnum|null $default;

    public static function supports(string $type): bool
    {
        $enum = ltrim($type, '?');
        if (BuiltInType::Mixed->value === $enum) {
            return true;
        }

        try {
            new ReflectionEnum(ltrim($type, '?'));

            return true;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @throws MappingFailed
     */
    public function __construct(
        string $propertyType,
        ?string $default = null,
        ?string $enum = null
    ) {
        if (!self::supports($propertyType)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not a PHP Enumeration.');
        }

        $enumClass = ltrim($propertyType, '?');
        if (BuiltInType::Mixed->value === $enumClass) {
            if (null === $enum || !self::supports($enum)) {
                throw new MappingFailed('The property type `'.$enum.'` is not a PHP Enumeration.');
            }

            $enumClass = $enum;
        }

        $this->class = $enumClass;
        $this->isNullable = str_starts_with($propertyType, '?');
        try {
            $this->default = (null !== $default) ? $this->cast($default) : $default;
        } catch (TypeCastingFailed $exception) {
            throw new MappingFailed('The configuration option for `'.self::class.'` are invalid.', 0, $exception);
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

            $backedValue = 'int' === $enum->getBackingType()?->getName() ? filter_var($value, FILTER_VALIDATE_INT) : $value;

            return $this->class::from($backedValue);
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast to `'.$this->class.'` the value `'.$value.'`.', 0, $exception);
        }
    }
}
