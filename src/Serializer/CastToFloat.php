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

use function filter_var;
use function str_starts_with;

/**
 * @implements TypeCasting<float|null>
 */
final class CastToFloat implements TypeCasting
{
    private readonly bool $isNullable;

    public function __construct(
        string $propertyType,
        private readonly ?float $default = null,
    ) {
        $baseType = Type::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(Type::Mixed, Type::Float)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `float` type is required.');
        }

        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?float
    {
        if (null === $value) {
            return match ($this->isNullable) {
                true => $this->default,
                false => throw new TypeCastingFailed('The `null` value can not be cast to a float; the property type is not nullable.'),
            };
        }

        $float = filter_var($value, Type::Float->filterFlag());

        return match ($float) {
            false => throw new TypeCastingFailed('The `'.$value.'` value can not be cast to a float.'),
            default => $float,
        };
    }
}
