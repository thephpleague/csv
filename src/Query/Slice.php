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

use ArrayIterator;
use Iterator;
use IteratorIterator;
use League\Csv\InvalidArgument;
use LimitIterator;

use function array_slice;
use function is_array;
use function iterator_to_array;

final class Slice
{
    public static function value(iterable $value, int $offset = 0, int $length = -1): LimitIterator
    {
        $iterator = match (true) {
            0 > $offset => throw InvalidArgument::dueToInvalidRecordOffset($offset, __METHOD__),
            -1 > $length => throw InvalidArgument::dueToInvalidLimit($length, __METHOD__),
            is_array($value) => new ArrayIterator($value),
            $value instanceof Iterator => $value,
            default => new IteratorIterator($value),
        };

        return new LimitIterator($iterator, $offset, $length);
    }

    public static function array(iterable $values, int $offset = 0, int $length = -1): array
    {
        return match (true) {
            0 > $offset => throw InvalidArgument::dueToInvalidRecordOffset($offset, __METHOD__),
            -1 > $length => throw InvalidArgument::dueToInvalidLimit($length, __METHOD__),
            default => array_slice(!is_array($values) ? iterator_to_array($values) : $values, $offset, $length),
        };
    }
}
