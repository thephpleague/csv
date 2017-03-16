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
use League\Csv\Exception\RuntimeException;
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
     * Set and selected columns to be used by the RecordSet object
     *
     * The array offset represents the CSV document header value
     * The array value represents the Alias named to be used by the RecordSet object
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
    public function offset(int $offset): self
    {
        $offset = $this->filterInteger($offset, 0, __METHOD__.': the offset must be a positive integer or 0');
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
    public function limit(int $limit): self
    {
        $limit = $this->filterInteger($limit, -1, __METHOD__.': the limit must an integer greater or equals to -1');
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
        list($columns, $combine) = $this->buildColumns($reader->getHeader());
        $iterator = $this->buildWhere($reader->getIterator());
        $iterator = $this->buildOrderBy($iterator);
        $iterator = new LimitIterator($iterator, $this->offset, $this->limit);
        if (null !== $combine) {
            $iterator = new MapIterator($iterator, $combine);
        }

        return new RecordSet($iterator, $columns);
    }

    /**
     * Add the CSV column if present
     *
     * @param string[] $columns
     *
     * @return array
     */
    protected function buildColumns(array $columns): array
    {
        if (empty($this->columns)) {
            return [$columns, null];
        }

        $columns_alias = $this->filterColumnAgainstCsvHeader($columns);
        $columns = array_values($columns_alias);
        $combine = function (array $record) use ($columns_alias): array {
            $res = [];
            foreach ($columns_alias as $key => $alias) {
                $res[$alias] = $record[$key] ?? null;
            }

            return $res;
        };

        return [$columns, $combine];
    }

    /**
     * Validate the column against the processed CSV header
     *
     * @param string[] $headers Reader CSV header
     *
     * @throws RuntimeException If a column is not found
     */
    protected function filterColumnAgainstCsvHeader(array $headers)
    {
        if (empty($headers)) {
            $filter = function ($key): bool {
                return !is_int($key) || $key < 0;
            };

            if (empty(array_filter($this->columns, $filter, ARRAY_FILTER_USE_KEY))) {
                return $this->columns;
            }

            throw new RuntimeException('If no header is specified the columns keys must contain only positive integer or 0');
        }

        $columns = $this->formatColumns($this->columns);
        foreach ($columns as $key => $alias) {
            if (false === array_search($key, $headers, true)) {
                throw new RuntimeException(sprintf('The `%s` column does not exist in the Csv document', $key));
            }
        }

        return $columns;
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

        $compare = function (array $record_a, array $record_b): int {
            foreach ($this->order_by as $callable) {
                if (0 !== ($cmp = $callable($record_a, $record_b))) {
                    return $cmp;
                }
            }

            return $cmp ?? 0;
        };

        $iterator = new ArrayIterator(iterator_to_array($iterator, true));
        $iterator->uasort($compare);

        return $iterator;
    }
}
