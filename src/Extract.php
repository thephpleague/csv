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
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_object;
use function lcfirst;
use function str_replace;

final class Extract
{
    /**
     * Tries to retrieve the value from an array or an object.
     *
     * If the value is an array and the key is an integer the content will be retrieved
     * from the array_values array form. Negative offset are supported.
     * If the value is an object, the key MUST be a string.
     *
     * @throws ReflectionException
     * @throws StatementError If the value can not be retrieved
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

        return array_key_exists($offset, $value) ? $value[$offset] : throw StatementError::dueToUnknownColumn($key, $value);
    }

    /**
     * @throws ReflectionException
     * @throws StatementError
     */
    private static function getObjectPropertyValue(object $value, string|int $key): mixed
    {
        if (is_int($key)) {
            throw StatementError::dueToUnknownColumn($key, $value);
        }

        $refl = new ReflectionObject($value);
        if ($refl->hasProperty($key) && $refl->getProperty($key)->isPublic()) {
            return $refl->getProperty($key)->getValue($value);
        }

        $methodNameList = [$key];
        if (($camelCasedKey = self::camelCase($key)) !== $key) {
            $methodNameList[] = $camelCasedKey;
        }
        $methodNameList[] = self::camelCase($key, 'get');
        foreach ($methodNameList as $methodName) {
            if ($refl->hasMethod($methodName)
                && $refl->getMethod($methodName)->isPublic()
                && 1 > $refl->getMethod($methodName)->getNumberOfRequiredParameters()
            ) {
                return $refl->getMethod($methodName)->invoke($value);
            }
        }

        if (method_exists($value, '__call')) {
            return $refl->getMethod('__call')->invoke($value, $methodNameList[1]);
        }

        if ($value instanceof ArrayAccess && $value->offsetExists($key)) {
            return $value->offsetGet($key);
        }

        throw StatementError::dueToUnknownColumn($key, $value);
    }

    private static function camelCase(string $value, string $prefix = ''): string
    {
        if ('' !== $prefix) {
            $prefix .= '_';
        }

        return lcfirst(implode('', array_map(
            ucfirst(...),
            explode(' ', str_replace(['-', '_'], ' ', $prefix.$value))
        )));
    }
}
