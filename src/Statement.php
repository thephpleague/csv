<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use ArrayIterator;
use CallbackFilterIterator;
use Iterator;
use League\Csv\Exception\OutOfRangeException;
use LimitIterator;

/**
 * A trait to manage filtering a CSV
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class Statement
{
    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $where = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
     */
    protected $order_by = [];

    /**
     * iterator Offset
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * iterator maximum length
     *
     * @var int
     */
    protected $limit = -1;

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return self
     */
    public function where(callable $callable): self
    {
        $clone = clone $this;
        $clone->where[] = $callable;

        return $clone;
    }

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return self
     */
    public function orderBy(callable $callable): self
    {
        $clone = clone $this;
        $clone->order_by[] = $callable;

        return $clone;
    }

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @throws OutOfRangeException if the offset is lesser than 0
     *
     * @return self
     */
    public function offset(int $offset): self
    {
        if (0 > $offset) {
            throw new OutOfRangeException(sprintf('%() expects the offset to be a positive integer or 0, %s given', __METHOD__, $offset));
        }

        if ($offset === $this->offset) {
            return $this;
        }

        $clone = clone $this;
        $clone->offset = $offset;

        return $clone;
    }

    /**
     * Set LimitIterator Count
     *
     * @param int $limit
     *
     * @throws OutOfRangeException if the limit is lesser than -1
     *
     * @return self
     */
    public function limit(int $limit): self
    {
        if (-1 > $limit) {
            throw new OutOfRangeException(sprintf('%s() expects the limit to be greater or equel to -1, %s given', __METHOD__, $limit));
        }

        if ($limit === $this->limit) {
            return $this;
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @param Reader   $csv
     * @param string[] $header an optional header to use instead of the CSV document header
     *
     * @return ResultSet
     */
    public function process(Reader $csv, array $header = []): ResultSet
    {
        if (empty($header)) {
            $header = $csv->getHeader();
        }

        $reducer = function (Iterator $iterator, callable $callable): Iterator {
            return new CallbackFilterIterator($iterator, $callable);
        };

        $iterator = array_reduce($this->where, $reducer, $csv->getRecords($header));
        $iterator = $this->buildOrderBy($iterator);
        $iterator = new LimitIterator($iterator, $this->offset, $this->limit);

        return new ResultSet($iterator, $header);
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function buildOrderBy(Iterator $iterator): Iterator
    {
        if (empty($this->order_by)) {
            return $iterator;
        }

        $compare = function (array $record_a, array $record_b): int {
            foreach ($this->order_by as $callable) {
                if (0 !== ($cmp = $callable($record_a, $record_b))) {
                    return $cmp;
                }
            }

            return $cmp ?? 0;
        };

        $iterator = new ArrayIterator(iterator_to_array($iterator));
        $iterator->uasort($compare);

        return $iterator;
    }
}
