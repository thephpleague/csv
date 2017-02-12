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
use LimitIterator;

/**
 *  A trait to manage filtering a CSV
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class Statement
{
    use ValidatorTrait;

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
     * CSV headers
     *
     * @var string[]
     */
    protected $header = [];

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return self
     */
    public function offset(int $offset = 0): self
    {
        $offset = $this->filterInteger($offset, 0, 'the offset must be a positive integer or 0');
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
     * @return self
     */
    public function limit(int $limit = -1): self
    {
        $limit = $this->filterInteger($limit, -1, 'the limit must an integer greater or equals to -1');
        if ($limit === $this->limit) {
            return $this;
        }

        $clone = clone $this;
        $clone->limit = $limit;

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
     * Set the headers to be used by the RecordSet object
     *
     * @param string[] $header
     *
     * @return self
     */
    public function header(array $header): self
    {
        $header = $this->filterHeader($header);
        if ($header === $this->header) {
            return $this;
        }

        $clone = clone $this;
        $clone->header = $header;

        return $clone;
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return RecordSet
     */
    public function process(Reader $reader): RecordSet
    {
        $header = $this->header;
        if (empty($header)) {
            $header = $reader->getHeader();
        }
        $iterator = $this->combineHeader($reader->getIterator());
        $iterator = $this->filterRecords($iterator);
        $iterator = $this->orderRecords($iterator);

        return new RecordSet(new LimitIterator($iterator, $this->offset, $this->limit), $header);
    }

    /**
     * Add the CSV header if present
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function combineHeader(Iterator $iterator): Iterator
    {
        if (empty($this->header)) {
            return $iterator;
        }

        $header_count = count($this->header);
        $combine = function (array $row) use ($header_count) {
            if ($header_count != count($row)) {
                $row = array_slice(array_pad($row, $header_count, null), 0, $header_count);
            }

            return array_combine($this->header, $row);
        };

        return new MapIterator($iterator, $combine);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function filterRecords(Iterator $iterator): Iterator
    {
        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };

        return array_reduce($this->where, $reducer, $iterator);
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function orderRecords(Iterator $iterator): Iterator
    {
        if (empty($this->order_by)) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($row_a, $row_b) {
            $res = 0;
            foreach ($this->order_by as $compare) {
                if (0 !== ($res = $compare($row_a, $row_b))) {
                    break;
                }
            }

            return $res;
        });

        return $obj;
    }
}
