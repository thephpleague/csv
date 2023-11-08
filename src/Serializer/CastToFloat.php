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

use const FILTER_VALIDATE_FLOAT;

/**
 * @implements TypeCasting<float|null>
 */
final class CastToFloat implements TypeCasting
{
    private readonly bool $isNullable;
    /** @var array{min_range?:float, max_range?:float, default?:float} */
    private readonly array $options;

    public static function supports(string $propertyType): bool
    {
        return BasicType::tryfromPropertyType($propertyType)
            ?->isOneOf(BasicType::Mixed, BasicType::Float)
            ?? false;
    }

    public function __construct(
        string $propertyType,
        private readonly ?float $default = null,
        ?float $min = null,
        ?float $max = null,
    ) {
        if (!self::supports($propertyType)) {
            throw new MappingFailed('The property type is not a built in basic type.');
        }

        if (null !== $max && null !== $min && $max < $min) {
            throw new MappingFailed('The maximum float value can not be lesser than the minimum float value.');
        }

        $this->options = array_filter(
            ['min_range' => $min, 'max_range' => $max, 'default' => $this->default],
            fn (?float $value) => null !== $value,
        );
        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?float
    {
        if (null !== $value) {
            if (false === ($floatValue = filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => $this->options]))) {
                throw new TypeCastingFailed('The `'.$value.'` value can not be cast to a float using the `'.self::class.'` options.');
            }

            return $floatValue;
        }

        if (!$this->isNullable) {
            throw new TypeCastingFailed('The `null` value can not be cast to a float using the `'.self::class.'` options.');
        }

        return $this->default;
    }
}
