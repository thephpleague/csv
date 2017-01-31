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

namespace League\Csv\Config;

use ArrayIterator;
use CallbackFilterIterator;
use Iterator;
use League\Csv\MapIterator;
use League\Csv\StreamIterator;
use LimitIterator;
use SplFileObject;

/**
 *  A trait to manage filtering a CSV
 *
 * @package League.csv
 * @since  9.0.0
 *
 */
trait StatementTrait
{
    use ValidatorTrait;

    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $iterator_filters = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
     */
    protected $iterator_sort_by = [];

    /**
     * iterator Offset
     *
     * @var int
     */
    protected $iterator_offset = 0;

    /**
     * iterator maximum length
     *
     * @var int
     */
    protected $iterator_limit = -1;

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return $this
     */
    public function setOffset(int $offset = 0): self
    {
        $this->iterator_offset = $this->filterInteger($offset, 0, 'the offset must be a positive integer or 0');

        return $this;
    }

    /**
     * Set LimitIterator Count
     *
     * @param int $limit
     *
     * @return $this
     */
    public function setLimit(int $limit = -1): self
    {
        $this->iterator_limit = $this->filterInteger($limit, -1, 'the limit must an integer greater or equals to -1');

        return $this;
    }

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addSortBy(callable $callable): self
    {
        $this->iterator_sort_by[] = $callable;

        return $this;
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFilter(callable $callable): self
    {
        $this->iterator_filters[] = $callable;

        return $this;
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return Iterator
     */
    public function getIterator()
    {
        $iterator = $this->getCsvDocument();
        $iterator->setCsvControl($this->getDelimiter(), $this->getEnclosure(), $this->getEscape());
        $iterator->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
        $iterator = $this->applyBomStripping($iterator);
        $iterator = $this->applyHeader($iterator);
        $iterator = $this->applyFilter($iterator);
        $iterator = $this->applySortBy($iterator);

        return $this->applyIteratorInterval($iterator);
    }

    /**
     * Returns the current field delimiter
     *
     * @return string
     */
    abstract public function getDelimiter(): string;

    /**
     * Returns the current field enclosure
     *
     * @return string
     */
    abstract public function getEnclosure(): string;

    /**
     * Returns the current field escape character
     *
     * @return string
     */
    abstract public function getEscape(): string;

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return StreamIterator|SplFileObject
     */
    abstract public function getCsvDocument();

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    abstract public function getInputBOM(): string;

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function applyBomStripping(Iterator $iterator): Iterator
    {
        $bom = $this->getInputBOM();
        if ('' == $bom) {
            return $iterator;
        }

        $bom_length = mb_strlen($bom);
        $enclosure = $this->getEnclosure();
        $strip_bom = function ($row, $index) use ($bom_length, $enclosure) {
            if (0 != $index || !is_array($row)) {
                return $row;
            }

            return $this->removeBOM($row, $bom_length, $enclosure);
        };

        return new MapIterator($iterator, $strip_bom);
    }

    /**
     * Returns the record offset used as header
     *
     * If no CSV record is used this method MUST return null
     *
     * @return int|null
     */
    abstract public function getHeaderOffset();

    /**
     * Returns the header
     *
     * If no CSV record is used this method MUST return an empty array
     *
     * @return string[]
     */
    abstract public function getHeader(): array;

    /**
     * Add the CSV header if present
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    public function applyHeader(Iterator $iterator): Iterator
    {
        $header = $this->getHeader();
        if (empty($header)) {
            return $iterator;
        }

        $header_count = count($header);
        $combine = function (array $row) use ($header, $header_count) {
            if ($header_count != count($row)) {
                $row = array_slice(array_pad($row, $header_count, null), 0, $header_count);
            }

            return array_combine($header, $row);
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
    protected function applyFilter(Iterator $iterator): Iterator
    {
        $header_offset = $this->getHeaderOffset();
        if (null !== $header_offset) {
            $strip_header = function ($row, $index) use ($header_offset) {
                return $index !== $header_offset;
            };
            array_unshift($this->iterator_filters, $strip_header);
        }

        $normalized_csv = function ($row) {
            return is_array($row) && $row != [null];
        };
        array_unshift($this->iterator_filters, $normalized_csv);

        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };
        $iterator = array_reduce($this->iterator_filters, $reducer, $iterator);
        $this->iterator_filters = [];

        return $iterator;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applySortBy(Iterator $iterator): Iterator
    {
        if (empty($this->iterator_sort_by)) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($row_a, $row_b) {
            $res = 0;
            foreach ($this->iterator_sort_by as $compare) {
                if (0 !== ($res = ($compare)($row_a, $row_b))) {
                    break;
                }
            }

            return $res;
        });
        $this->iterator_sort_by = [];

        return $obj;
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function applyIteratorInterval(Iterator $iterator): Iterator
    {
        $offset = $this->iterator_offset;
        $limit = $this->iterator_limit;
        $this->iterator_limit = -1;
        $this->iterator_offset = 0;

        return new LimitIterator($iterator, $offset, $limit);
    }
}
