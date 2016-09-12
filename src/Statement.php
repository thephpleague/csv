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
namespace League\Csv;

use ArrayIterator;
use CallbackFilterIterator;
use InvalidArgumentException;
use Iterator;
use League\Csv\Config\Validator;
use LimitIterator;

/**
 * A simple Statement class to fetch rows against a Csv file object
 *
 * @package League.csv
 * @since  9.0.0
 *
 */
class Statement
{
    use Validator;

    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $filters = [];

    /**
     * Callables to sort the iterator
     *
     * @var callable[]
     */
    protected $sort_by = [];

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
     * @inheritdoc
     */
    public function __set($property, $value)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * @inheritdoc
     */
    public function __unset($property)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * Returns a Record object
     *
     * @return RecordSet
     */
    public function process(AbstractCsv $csv)
    {
        $input_encoding = $csv->getInputEncoding();
        $use_converter = $this->useInternalConverter($csv);
        $header = $csv->getHeader();
        $header_offset = $csv->getHeaderOffset();
        $filters = [];
        if (null !== $header_offset) {
            $filters[] = function ($row, $index) use ($header_offset) {
                return $index !== $header_offset;
            };
        }

        $iterator = $this->format($csv, $header);
        $iterator = $this->convert($iterator, $input_encoding, $use_converter);
        $iterator = $this->filter($iterator, $filters);
        $iterator = $this->sort($iterator);

        return new RecordSet(new LimitIterator($iterator, $this->offset, $this->limit), $header);
    }

    /**
     * Prepare the csv for manipulation
     *
     * - remove the BOM sequence if present
     * - attach the header to the records if present
     *
     * @param AbstractCsv $csv
     *
     * @throws InvalidRowException if the column is inconsistent
     *
     * @return Iterator
     */
    protected function format(AbstractCsv $csv, array $header)
    {
        $iterator = $this->removeBOM($csv);
        if (empty($header)) {
            return $iterator;
        }

        $header_column_count = count($header);
        $combine_array = function (array $row) use ($header, $header_column_count) {
            if ($header_column_count != count($row)) {
                throw new InvalidRowException('csv_consistency', $row, 'The record and header column count differ');
            }

            return array_combine($header, $row);
        };

        return new MapIterator($iterator, $combine_array);
    }

    /**
     * Remove the BOM sequence from the CSV
     *
     * @param AbstractCsv $csv
     *
     * @return Iterator
     */
    protected function removeBOM(AbstractCsv $csv)
    {
        $bom = $csv->getInputBOM();
        if ('' === $bom) {
            return $csv->getIterator();
        }

        $enclosure = $csv->getEnclosure();
        $strip_bom = function ($row, $index) use ($bom, $enclosure) {
            if (0 != $index) {
                return $row;
            }

            return $this->stripBOM($row, $bom, $enclosure);
        };

        return new MapIterator($csv->getIterator(), $strip_bom);
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function filter(Iterator $iterator, array $filters)
    {
        $reducer = function ($iterator, $callable) {
            return new CallbackFilterIterator($iterator, $callable);
        };

        $filters[] = function ($row) {
            return is_array($row) && $row != [null];
        };

        return array_reduce(array_merge($filters, $this->filters), $reducer, $iterator);
    }

    /**
    * Sort the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function sort(Iterator $iterator)
    {
        if (empty($this->sort_by)) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator));
        $obj->uasort(function ($row_a, $row_b) {
            $res = 0;
            foreach ($this->sort_by as $compare) {
                if (0 !== ($res = call_user_func($compare, $row_a, $row_b))) {
                    break;
                }
            }

            return $res;
        });

        return $obj;
    }

    /**
     * Convert Iterator to UTF-8
     *
     * @param Iterator $iterator
     * @param string   $input_encoding
     * @param bool     $use_converter
     *
     * @return Iterator
     */
    protected function convert(Iterator $iterator, $input_encoding, $use_converter)
    {
        if (!$use_converter) {
            return $iterator;
        }

        $convert_row = function ($row) use ($input_encoding) {
            return $this->convertRecordToUtf8($row, $input_encoding);
        };

        return new MapIterator($iterator, $convert_row);
    }

    /**
     * Set LimitIterator Offset
     *
     * @param $offset
     *
     * @return static
     */
    public function setOffset($offset)
    {
        $offset = $this->validateInteger($offset, 0, 'the offset must be a positive integer or 0');
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
     * @return static
     */
    public function setLimit($limit)
    {
        $limit = $this->validateInteger($limit, -1, 'the limit must an integer greater or equals to -1');
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
     * @return static
     */
    public function addSortBy(callable $callable)
    {
        $clone = clone $this;
        $clone->sort_by[] = $callable;

        return $clone;
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return static
     */
    public function addFilter(callable $callable)
    {
        $clone = clone $this;
        $clone->filters[] = $callable;

        return $clone;
    }
}
