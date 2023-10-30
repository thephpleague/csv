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

use ArrayIterator;
use Iterator;
use League\Csv\TypeCasting\TypeCastingFailed;
use ReflectionException;

class Mapper
{
    /**
     * @param class-string $className
     */
    public function __construct(private readonly string $className)
    {
    }

    /**
     * @throws TypeCastingFailed
     * @throws ReflectionException
     */
    public function map(TabularDataReader $tabularDataReader): Iterator
    {
        return $this($tabularDataReader, $tabularDataReader->getHeader());
    }

    /**
     * @throws TypeCastingFailed
     * @throws ReflectionException
     */
    public function __invoke(iterable $records, array $header): Iterator
    {
        $mapper = new RecordMapper($this->className, $header);

        return match (true) {
            is_array($records) => new MapIterator(new ArrayIterator($records), $mapper(...)),
            default =>  new MapIterator($records, $mapper(...)),
        };
    }
}
