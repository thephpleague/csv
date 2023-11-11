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
use Throwable;
use UnitEnum;

use function ltrim;
use function str_starts_with;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    private readonly string $class;
    private readonly bool $isNullable;
    private readonly BackedEnum|UnitEnum|null $default;

    /**
     * @throws MappingFailed
     */
    public function __construct(
        string $propertyType,
        ?string $default = null,
    ) {
        $baseType = Type::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(Type::Mixed, Type::Enum)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; an `Enum` is required.');
        }

        $this->class = ltrim($propertyType, '?');
        $this->isNullable = str_starts_with($propertyType, '?');
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
}
