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

/**
 * @implements TypeCasting<int|float|bool|string|null>
 */
final class CastToBuiltInType implements TypeCasting
{
    private readonly string $class;
    private readonly bool $isNullable;

    public static function supports(string $propertyType): bool
    {
        return null !== BuiltInType::tryFrom(ltrim($propertyType, '?'));
    }

    public function __construct(string $propertyType)
    {
        if (!self::supports($propertyType)) {
            throw new TypeCastingFailed('The property type is not a built int basic type.');
        }

        $this->class = ltrim($propertyType, '?');
        $this->isNullable = str_starts_with($propertyType, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): int|float|bool|string|null
    {
        return match(true) {
            in_array($value, ['', null], true) && $this->isNullable => null,
            default => BuiltInType::from($this->class)->cast($value),
        };
    }
}
