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

use BackedEnum;
use DateTimeInterface;
use ReflectionEnum;
use ReflectionException;
use Throwable;
use UnitEnum;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    public static function supports(string $type): bool
    {
        try {
            new ReflectionEnum(ltrim($type, '?'));

            return true;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value, string $type): BackedEnum|UnitEnum|null
    {
        if (!self::supports($type)) {
            throw new TypeCastingFailed('The property type `'.$type.'` is not a PHP Enumeration.');
        }

        if (null === $value) {
            return match (true) {
                str_starts_with($type, '?'), => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        $enumName = ltrim($type, '?');

        try {
            $enum = new ReflectionEnum($enumName);
            if (!$enum->isBacked()) {
                return $enum->getCase($value)->getValue();
            }

            $backedValue = 'int' === $enum->getBackingType()?->getName() ? filter_var($value, FILTER_VALIDATE_INT) : $value;

            return $enumName::from($backedValue);
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast to `'.$enumName.'` the value `'.$value.'`.', 0, $exception);
        }
    }
}
