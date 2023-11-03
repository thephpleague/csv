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
use ReflectionEnum;
use ReflectionException;
use Throwable;
use UnitEnum;

/**
 * @implements TypeCasting<BackedEnum|UnitEnum|null>
 */
class CastToEnum implements TypeCasting
{
    private readonly string $class;
    private readonly bool $isNullable;

    public static function supports(string $type): bool
    {
        try {
            new ReflectionEnum(ltrim($type, '?'));

            return true;
        } catch (ReflectionException) {
            return false;
        }
    }

    public function __construct(string $type)
    {
        if (!self::supports($type)) {
            throw new TypeCastingFailed('The property type `'.$type.'` is not a PHP Enumeration.');
        }

        $this->class = ltrim($type, '?');
        $this->isNullable = str_starts_with($type, '?');
    }

    /**
     * @throws TypeCastingFailed
     */
    public function toVariable(?string $value): BackedEnum|UnitEnum|null
    {

        if (null === $value) {
            return match (true) {
                $this->isNullable, => null,
                default => throw new TypeCastingFailed('Unable to convert the `null` value.'),
            };
        }

        try {
            $enum = new ReflectionEnum($this->class);
            if (!$enum->isBacked()) {
                return $enum->getCase($value)->getValue();
            }

            $backedValue = 'int' === $enum->getBackingType()?->getName() ? filter_var($value, FILTER_VALIDATE_INT) : $value;

            return $this->class::from($backedValue);
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast to `'.$this->class.'` the value `'.$value.'`.', 0, $exception);
        }
    }
}
