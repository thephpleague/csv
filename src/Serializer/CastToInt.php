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

use const FILTER_VALIDATE_INT;

/**
 * @implements TypeCasting<int|float|bool|string|null>
 */
final class CastToInt implements TypeCasting
{
    private readonly bool $isNullable;
    /** @var array{min_range?:int, max_range?:int, default?:int} */
    private readonly array $options;

    public static function supports(string $propertyType): bool
    {
        return BasicType::tryFromPropertyType($propertyType)
            ?->isOneOf(BasicType::Mixed, BasicType::Int)
            ?? false;
    }

    public function __construct(
        string $propertyType,
        private readonly ?int $default = null,
        ?int $min = null,
        ?int $max = null,
    ) {
        if (!self::supports($propertyType)) {
            throw new MappingFailed('The property type is not a built in basic type.');
        }

        if (null !== $max && null !== $min && $max < $min) {
            throw new MappingFailed('The maximum integer value can not be lesser than the minimum integer value.');
        }

        $this->options = array_filter(
            ['min_range' => $min, 'max_range' => $max, 'default' => $this->default],
            fn (?int $value) => null !== $value,
        );
        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?int
    {
        if (null !== $value) {
            if (false === ($floatValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => $this->options]))) {
                throw new TypeCastingFailed('The `'.$value.'` value can not be cast to an integer using the `'.self::class.'` options.');
            }

            return $floatValue;
        }

        if (!$this->isNullable) {
            throw new TypeCastingFailed('The `null` value can not be cast to an integer using the `'.self::class.'` options.');
        }

        return $this->default;
    }
}
