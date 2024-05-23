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

final class Limit
{
    private function __construct(
        public readonly int $offset,
        public readonly int $length,
    ){
        if (0 > $this->offset) {
            throw new QueryException(self::class.' expects the offset to be greater or equal to 0, '.$this->offset.' given.');
        }

        if (-1 > $this->length) {
            throw new QueryException(self::class.' expects the length to be greater or equal to -1, '.$this->length.' given.');
        }
    }

    public static function new(int $offset, int $length): self
    {
        return new self($offset, $length);
    }

    /**
     * Allows iteration over a limited subset of items in an iterable structure.
     */
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
}
