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

use function str_starts_with;

/**
 * @implements TypeCasting<string|null>
 */
final class CastToString implements TypeCasting
{
    private readonly bool $isNullable;

    public function __construct(
        string $propertyType,
        private readonly ?string $default = null
    ) {
        $baseType = BasicType::tryFromPropertyType($propertyType);
        if (null === $baseType || !$baseType->isOneOf(BasicType::Mixed, BasicType::String)) {
            throw new MappingFailed('The property type `'.$propertyType.'` is not supported; a `string` type is required.');
        }

        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?string
    {
        return match(true) {
            null !== $value => $value,
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('The `null` value can not be cast to a string.'),
        };
    }
}
