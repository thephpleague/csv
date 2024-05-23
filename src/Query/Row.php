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

namespace League\Csv\Query;

use ArrayAccess;
use ReflectionException;
use ReflectionObject;
use TypeError;

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

final class Row
{
    public static function from(mixed $value): Row
    {
        return new self(match (true) {
            is_object($value),
            is_array($value) => $value,
            default => throw new TypeError('The value must be an array or an object; received '.gettype($value).'.'),
        });
    }

    private function __construct(private readonly array|object $row)
    {
    }

    /**
     * Tries to retrieve a single value from a record.
     *
     * @throws ReflectionException
     * @throws QueryException If the value can not be retrieved
     * @see Row::select()
     *
     */
    public function value(string|int $key): mixed
    {
        return $this->select($key)[$key];
    }

    /**
     * Tries to retrieve multiple values from a record.
     *
     * If the value is an array and the key is an integer the content will be retrieved
     * from the array_values array form. Negative offset are supported.
     * If the value is an object, the key MUST be a string.
     *
     * @throws ReflectionException
     * @throws QueryException If the value can not be retrieved
     *
     * @return non-empty-array<array-key, mixed>
     */
    public function select(string|int ...$key): array
    {
        return match (true) {
            is_object($this->row) => self::getObjectPropertyValue($this->row, ...$key),
            default => self::getArrayEntry($this->row, ...$key),
        };
    }

    /**
     * @throws QueryException
     *
     * @return non-empty-array<array-key, mixed>
     */
    private function getArrayEntry(array $row, string|int ...$keys): array
    {
        $res = [];
        $arrValues = array_values($row);
        foreach ($keys as $key) {
            if (array_key_exists($key, $res)) {
                continue;
            }
            $offset = $key;
            if (is_int($offset)) {
                if (!array_is_list($row)) {
                    $row = $arrValues;
                }

                if ($offset < 0) {
                    $offset += count($row);
                }
            }

            $res[$key] = array_key_exists($offset, $row) ? $row[$offset] : throw QueryException::dueToUnknownColumn($key, $row);
        }

        return [] !== $res ? $res : throw QueryException::dueToMissingColumn();
    }

    /**
     * @throws ReflectionException
     * @throws QueryException
     *
     * @return non-empty-array<array-key, mixed>
     */
    private static function getObjectPropertyValue(object $row, string|int ...$keys): array
    {
        $res = [];
        $object = new ReflectionObject($row);
        foreach ($keys as $key) {
            if (array_key_exists($key, $res)) {
                continue;
            }

            if (is_int($key)) {
                throw QueryException::dueToUnknownColumn($key, $row);
            }

            if ($object->hasProperty($key) && $object->getProperty($key)->isPublic()) {
                $res[$key] = $object->getProperty($key)->getValue($row);
                continue;
            }

            $methodNameList = [$key];
            if (($camelCasedKey = self::camelCase($key)) !== $key) {
                $methodNameList[] = $camelCasedKey;
            }
            $methodNameList[] = self::camelCase($key, 'get');
            foreach ($methodNameList as $methodName) {
                if ($object->hasMethod($methodName)
                    && $object->getMethod($methodName)->isPublic()
                    && 1 > $object->getMethod($methodName)->getNumberOfRequiredParameters()
                ) {
                    $res[$key] = $object->getMethod($methodName)->invoke($row);
                    continue 2;
                }
            }

            if (method_exists($row, '__call')) {
                $res[$key] = $object->getMethod('__call')->invoke($row, $methodNameList[1]);
                continue;
            }

            if ($row instanceof ArrayAccess && $row->offsetExists($key)) {
                $res[$key] =  $row->offsetGet($key);
                continue;
            }

            throw QueryException::dueToUnknownColumn($key, $row);
        }

        return [] !== $res ? $res : throw QueryException::dueToMissingColumn();
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
