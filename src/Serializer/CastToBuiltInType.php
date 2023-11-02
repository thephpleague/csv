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
    public static function supports(string $type): bool
    {
        return null !== BuiltInType::tryFrom(ltrim($type, '?'));
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value, string $type): int|float|bool|string|null
    {
        return match(true) {
            in_array($value, ['', null], true) && str_starts_with($type, '?') => null,
            default => BuiltInType::from(ltrim($type, '?'))->cast($value),
        };
    }
}
