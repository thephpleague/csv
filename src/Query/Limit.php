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
use LimitIterator;
use Traversable;

use function array_slice;
use function is_array;
use function iterator_to_array;

final class Limit
{
    private function __construct(
        public readonly int $offset,
        public readonly int $length,
    ){
        if (0 > $this->offset) {
            throw new QueryException(__METHOD__.'() expects the offset to be greater or equal to 0, '.$this->offset.' given.');
        }

        if (-1 > $this->length) {
            throw new QueryException(__METHOD__.'() expects the length to be greater or equal to -1, '.$this->length.' given.');
        }
    }

    public static function new(int $offset, int $length): self
    {
        return new self($offset, $length);
    }

    public function slice(iterable $value): LimitIterator
    {
        return new LimitIterator(
            match (true) {
                $value instanceof Iterator => $value,
                $value instanceof Traversable => new IteratorIterator($value),
                default => new ArrayIterator($value),
            },
            $this->offset,
            $this->length,
        );
    }

    public function sliceArray(iterable $values, int $offset = 0, int $length = -1): array
    {
        return array_slice(
            !is_array($values) ? iterator_to_array($values) : $values,
            $this->offset,
            $this->length === -1 ? null : $length,
            true
        );
    }
}
