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

final class CastToBool implements TypeCasting
{
    private readonly bool $isNullable;

    public static function supports(string $propertyType): bool
    {
        return BasicType::tryfromPropertyType($propertyType)
            ?->isOneOf(BasicType::Mixed, BasicType::Bool)
            ?? false;
    }

    public function __construct(
        string $propertyType,
        private readonly ?bool $default = null
    ) {
        if (!self::supports($propertyType)) {
            throw new MappingFailed('The property type is not a built in bool type or mixed.');
        }

        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): ?bool
    {
        return match(true) {
            null !== $value => filter_var($value, FILTER_VALIDATE_BOOL),
            $this->isNullable => $this->default,
            default => throw new TypeCastingFailed('The `null` value can not be cast to a boolean value.'),
        };
    }
}
