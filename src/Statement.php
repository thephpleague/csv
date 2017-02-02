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
use League\Csv\Config\ValidatorTrait;
use LimitIterator;
use SplFileObject;

/**
 *  A trait to manage filtering a CSV
 *
 * @package League.csv
 * @since  9.0.0
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
    protected $headers = [];

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return self
     */
    public function offset(int $offset = 0): self
    {
        $this->offset = $this->filterInteger($offset, 0, 'the offset must be a positive integer or 0');

        return $this;
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
        $this->limit = $this->filterInteger($limit, -1, 'the limit must an integer greater or equals to -1');

        return $this;
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
        $this->order_by[] = $callable;

        return $this;
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
        $this->where[] = $callable;

        return $this;
    }

    /**
     * Set the headers to be used by the RecordSet object
     *
     * @param string[] $headers
     *
     * @return self
     */
    public function headers(array $headers): self
    {
        $this->headers = $this->filterHeader($headers);

        return $this;
    }

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return RecordSet
     */
    public function process(Reader $reader)
    {
        $bom = $reader->getInputBOM();
        $enclosure = $reader->getEnclosure();
        $this->prepare($reader);

        $csv = $reader->getDocument();
        $csv->setFlags(SplFileObject::READ_AHEAD | SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $iterator = $this->stripBOM($csv, $bom, $enclosure);
        $iterator = $this->addHeader($iterator);
        $iterator = $this->filterRecords($iterator);
        $iterator = $this->orderRecords($iterator);

        return new RecordSet(new LimitIterator($iterator, $this->offset, $this->limit), $this->headers);
    }

    /**
     * Set the computed RecordSet headers
     *
     * @param Reader $reader
     */
    protected function prepare(Reader $reader)
    {
        if (!empty($this->headers)) {
            return;
        }

        $this->headers = $reader->getHeader();
        $header_offset = $reader->getHeaderOffset();
        if (null !== $header_offset) {
            $strip_header = function ($row, $index) use ($header_offset) {
                return $index !== $header_offset;
            };
            array_unshift($this->where, $strip_header);
        }
    }

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function stripBOM(Iterator $iterator, string $bom, string $enclosure): Iterator
    {
        if ('' == $bom) {
            return $iterator;
        }

        $bom_length = mb_strlen($bom);
        $strip_bom = function ($row, $index) use ($bom_length, $enclosure) {
            if (0 != $index || !is_array($row)) {
                return $row;
            }

            return $this->removeBOM($row, $bom_length, $enclosure);
        };

        return new MapIterator($iterator, $strip_bom);
    }

    /**
     * Add the CSV header if present
     *
     * @param Iterator $iterator
     *
     * @return Iterator
     */
    protected function addHeader(Iterator $iterator): Iterator
    {
        if (empty($this->headers)) {
            return $iterator;
        }

        $header_count = count($this->headers);
        $combine = function (array $row) use ($header_count) {
            if ($header_count != count($row)) {
                $row = array_slice(array_pad($row, $header_count, null), 0, $header_count);
            }

            return array_combine($this->headers, $row);
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
        $normalized_csv = function ($row) {
            return is_array($row) && $row != [null];
        };
        array_unshift($this->where, $normalized_csv);

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
