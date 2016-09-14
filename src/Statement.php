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
 * A immutable value object to generate statements
 * to select records against a {@link Reader} object
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
     * Return the added filter callable functions
     *
     * @return callable[]
     */
    public function getFilter()
    {
        return $this->filters;
    }

    /**
     * Return the added sorting callable functions
     *
     * @return callable[]
     */
    public function getSortBy()
    {
        return $this->sort_by;
    }

    /**
     * Return the limit clause
     *
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Return the offset clause
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Set the offset clause
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
     * Set the limit clause
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
     * Add a sorting callable function
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
     *Add a filter callable function
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
     * Returns a Record object
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
