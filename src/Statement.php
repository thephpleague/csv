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
     * CSV columns name
     *
     * @var array
     */
    protected $columns = [];

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
     * Set and select the column to be used by the RecordSet object
     *
     * @param array $columns
     *
     * @return self
     */
    public function columns(array $columns): self
    {
        $columns = $this->filterColumnNames($columns);
        if ($columns === $this->columns) {
            return $this;
        }

        $clone = clone $this;
        $clone->columns = $columns;

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
     * Returns the inner CSV Document Iterator object
     *
     * @param Reader $reader
     *
     * @return RecordSet
     */
    public function process(Reader $reader): RecordSet
    {
        list($columns, $iterator) = $this->buildColumns($reader);
        $iterator = $this->buildWhere($iterator);
        $iterator = $this->buildOrderBy($iterator);

        return new RecordSet(new LimitIterator($iterator, $this->offset, $this->limit), $columns);
    }

    /**
     * Add the CSV column if present
     *
     * @param Reader $reader
     *
     * @return array
     */
    protected function buildColumns(Reader $reader): array
    {
        $header = $reader->getHeader();
        $iterator = $reader->getIterator();
        if (empty($this->columns)) {
            return [$header, $iterator];
        }

        $columns = $this->filterColumnAgainstCsvHeader($header);
        $combine = function (array $row) use ($columns): array {
            $record = [];
            foreach ($columns as $key => $alias) {
                $record[$alias] = $row[$key] ?? null;
            }

            return $record;
        };

        return [array_values($columns), new MapIterator($iterator, $combine)];
    }

    /**
     * Format the column array
     *
     * @param array $columns
     *
     * @return array
     */
    private function formatColumns(array $columns): array
    {
        $res = [];
        foreach ($columns as $key => $alias) {
            $res[!is_string($key) ? $alias : $key] = $alias;
        }

        return $res;
    }

    /**
     * Validate the column against the processed CSV header
     *
     * @param array    $columns Statement CSV columns
     * @param string[] $headers Reader CSV header
     *
     * @throws Exception If a column is not found
     */
    protected function filterColumnAgainstCsvHeader(array $headers)
    {
        if (empty($headers)) {
            return $this->columns;
        }

        $columns = $this->formatColumns($this->columns);
        foreach ($columns as $key => $alias) {
            if (false === array_search($key, $headers, true)) {
                throw new Exception(sprintf('The following column `%s` does not exist in the CSV document', $key));
            }
        }

        return $columns;
    }

    /**
    * Filter the Iterator
    *
    * @param Iterator $iterator
    *
    * @return Iterator
    */
    protected function buildWhere(Iterator $iterator): Iterator
    {
        $reducer = function (Iterator $iterator, callable $callable): Iterator {
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
    protected function buildOrderBy(Iterator $iterator): Iterator
    {
        if (empty($this->order_by)) {
            return $iterator;
        }

        $obj = new ArrayIterator(iterator_to_array($iterator, true));
        $obj->uasort(function (array $record_a, array $record_b): int {
            $res = 0;
            foreach ($this->order_by as $compare) {
                if (0 !== ($res = $compare($record_a, $record_b))) {
                    break;
                }
            }

            return $res;
        });

        return $obj;
    }
}
