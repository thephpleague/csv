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
        $type = BuiltInType::tryFrom(ltrim($propertyType, '?'));

        return null !== $type && (BuiltInType::Mixed === $type || BuiltInType::Bool === $type);
    }

    public function __construct(
        string $propertyType,
        private readonly ?bool $default = null
    ) {
        if (!self::supports($propertyType)) {
            throw new TypeCastingFailed('The property type is not a built in basic type.');
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
