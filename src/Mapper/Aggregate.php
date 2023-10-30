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

use ArrayIterator;
use Iterator;
use League\Csv\MapIterator;
use League\Csv\TabularDataReader;
use ReflectionException;

class Aggregate
{
    /**
     * @param class-string $className
     *
     * @throws TypeCastingFailed
     * @throws ReflectionException
     */
    public static function map(string $className, TabularDataReader $tabularDataReader): Iterator
    {
        return self::deserialize($className, $tabularDataReader, $tabularDataReader->getHeader());
    }

    /**
     * @param class-string $className
     *
     * @throws TypeCastingFailed
     * @throws ReflectionException
     */
    public static function deserialize(string $className, iterable $records, array $header = []): Iterator
    {
        if (is_array($records)) {
            $records = new ArrayIterator($records);
        }

        return new MapIterator($records, (new Serializer($className, $header))->deserialize(...));
    }
}
