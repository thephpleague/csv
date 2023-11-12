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
 * @implements TypeCasting<int|null>
 */
final class CastToInt implements TypeCasting
{
    private readonly bool $isNullable;

    public function __construct(
        string $propertyType,
        private readonly ?int $default = null,
    ) {
        $baseType = Type::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(Type::Mixed, Type::Int, Type::Float)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `int` type is required.');
        }

        $this->isNullable = $baseType->equals(Type::Mixed) || str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?int
    {
        if (null === $value) {
            return match ($this->isNullable) {
                true => $this->default,
                false => throw new TypeCastingFailed('The `null` value can not be cast to an integer; the property type is not nullable.'),
            };
        }

        $int = filter_var($value, Type::Int->filterFlag());

        return match ($int) {
            false => throw new TypeCastingFailed('The `'.$value.'` value can not be cast to an integer.'),
            default => $int,
        };
    }
}
