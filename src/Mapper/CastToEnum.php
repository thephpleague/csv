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

namespace League\Csv\Mapper;

use BackedEnum;
use ReflectionEnum;
use Throwable;
use UnitEnum;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value, string $type): BackedEnum|UnitEnum|null
    {
        if (in_array($value, ['', null], true)) {
            return match (true) {
                str_starts_with($type, '?') => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        $enumName = ltrim($type, '?');

        try {
            $enum = new ReflectionEnum($enumName);
            if (!$enum->isBacked()) {
                return $enum->getCase($value)->getValue();
            }

            $backedValue = 'int' === $enum->getBackingType()?->getName() ? (int) $value : $value;

            return $enumName::from($backedValue);
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast to `'.$enumName.'` the value `'.$value.'`.', 0, $exception);
        }
    }
}
