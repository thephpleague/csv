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

use InvalidArgumentException;
use Iterator;
use League\Csv\Config\Validator;

/**
 * Immutable value object to generate statements
 * to select records against a {@link Reader} object
 *
 * @package League.csv
 * @since  9.0.0
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
     * Triggered when writing data to inaccessible properties
     *
     * @param string $property property name
     * @param mixed  $value    property value
     *
     * @throws InvalidArgumentException for all undefined properties
     */
    public function __set($property, $value)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * Triggered when __unset is used on inaccessible properties
     *
     * @param string $property property name
     *
     * @throws InvalidArgumentException for all undefined properties
     */
    public function __unset($property)
    {
        throw new InvalidArgumentException(sprintf('%s is an undefined property', $property));
    }

    /**
     * Returns the added filter callable functions
     *
     * @return callable[]
     */
    public function getFilter()
    {
        return $this->filters;
    }

    /**
     * Returns the added sorting callable functions
     *
     * @return callable[]
     */
    public function getSortBy()
    {
        return $this->sort_by;
    }

    /**
     * Returns the limit clause
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Returns the offset clause
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Sets the offset clause
     *
     * @param $offset
     *
     * @return static
     */
    public function setOffset($offset)
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
     * Sets the limit clause
     *
     * @param int $limit
     *
     * @return static
     */
    public function setLimit($limit)
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
     * Adds a sorting callable function
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
     *Adds a filter callable function
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

    /**
     * Returns a collection of selected records
     * from the submitted {@link Reader} object
     *
     * @param Reader $csv
     *
     * @return RecordSet
     */
    public function process(Reader $csv)
    {
        return new RecordSet($csv, $this);
    }
}
