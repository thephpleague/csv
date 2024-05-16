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

namespace League\Csv;

use ArrayAccess;
use ReflectionException;
use ReflectionObject;

use function array_is_list;
use function array_key_exists;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_object;
use function ucfirst;

final class Extract
{
    /**
     * @throws ReflectionException
     * @throws StatementError
     */
    public static function value(mixed $value, string|int $key): mixed
    {
        return match (true) {
            is_object($value) => self::getObjectPropertyValue($value, $key),
            is_array($value) => self::getArrayEntry($value, $key),
            default => throw new StatementError('The value must be an array or an object; received '.gettype($value).'.'),
        };
    }

    /**
     * @throws StatementError
     */
    private static function getArrayEntry(array $value, string|int $key): mixed
    {
        $offset = $key;
        if (is_int($offset)) {
            if (!array_is_list($value)) {
                $value = array_values($value);
            }

            if ($offset < 0) {
                $offset += count($value);
            }
        }

        return array_key_exists($offset, $value) ? $value[$offset] : throw StatementError::dueToUnknownColumn($key);
    }

    /**
     * @throws ReflectionException
     * @throws StatementError
     */
    private static function getObjectPropertyValue(object $value, string|int $key): mixed
    {
        if ($value instanceof ArrayAccess && $value->offsetExists($key)) {
            return $value->offsetGet($key);
        }

        if (is_int($key) || '' === $key) {
            throw new StatementError('The property name must be an non-empty string.');
        }

        $refl = new ReflectionObject($value);
        if ($refl->hasProperty($key) && $refl->getProperty($key)->isPublic()) {
            return $refl->getProperty($key)->getValue($value);
        }

        $methodName = 'get'.ucfirst($key);
        if ($refl->hasMethod($methodName)
            && $refl->getMethod($methodName)->isPublic()
            && 1 > $refl->getMethod($methodName)->getNumberOfRequiredParameters()
        ) {
            return $refl->getMethod($methodName)->invoke($value);
        }

        throw new StatementError('The property value could not be found.');
    }
}
