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

final class CastToBool implements TypeCasting
{
    private readonly bool $isNullable;

    public function __construct(
        string $propertyType,
        private readonly ?bool $default = null
    ) {
        $baseType = Type::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(Type::Mixed, Type::Bool)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `bool` type is required.');
        }

        $this->isNullable = $baseType->equals(Type::Mixed) || str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?bool
    {
        return match(true) {
            null !== $value => filter_var($value, Type::Bool->filterFlag()),
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('The `null` value can not be cast to a boolean value.'),
        };
    }
}
