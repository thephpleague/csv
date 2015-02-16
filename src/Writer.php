<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use InvalidArgumentException;
use Traversable;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  4.0.0
 *
 */
class Writer extends AbstractCsv
{
    /**
     * {@ihneritdoc}
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * The CSV object holder
     *
     * @var \SplFileObject
     */
    protected $csv;

    /**
     * Callables to filter the iterator
     *
     * @var callable[]
     */
    protected $rules = [];

    /**
     * should the class validate the input before insertion
     *
     * @var boolean
     */
    protected $useValidation = true;


    /**
     * Tells wether the library should check or not the input
     *
     * @param  bool $status
     *
     * @return static
     */
    public function useValidation($activate)
    {
        $this->useValidation = (bool) $activate;

        return $this;
    }

    /**
     * Set an Iterator sorting callable function
     *
     * @param callable $callable
     *
     * @return static
     */
    public function addValidationRule(callable $callable)
    {
        $this->rules[] = $callable;

        return $this;
    }

    /**
     * Remove a callable from the collection
     *
     * @param callable $callable
     *
     * @return static
     */
    public function removeValidationRule(callable $callable)
    {
        $res = array_search($callable, $this->rules, true);
        if (false !== $res) {
            unset($this->rules[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable is already registered
     *
     * @param callable $callable
     *
     * @return bool
     */
    public function hasValidationRule(callable $callable)
    {
        return false !== array_search($callable, $this->rules, true);
    }

    /**
     * Remove all registered callable
     *
     * @return static
     */
    public function clearValidationRules()
    {
        $this->rules = [];

        return $this;
    }

    /**
    * Filter the Iterator
    *
    * @param array $row
    *
    * @return array
    */
    protected function applyValidationRules(array $row)
    {
        if (! $this->useValidation || ! $this->rules) {
            return $row;
        }

        foreach ($this->rules as $rule) {
            $row = $rule($row);
        }

        return $row;
    }

    /**
     * Add multiple lines to the CSV your are generating
     *
     * a simple helper/Wrapper method around insertOne
     *
     * @param \Traversable|array $rows a multidimentional array or a Traversable object
     *
     * @throws \InvalidArgumentException If the given rows format is invalid
     *
     * @return static
     */
    public function insertAll($rows)
    {
        if (! is_array($rows) && ! $rows instanceof Traversable) {
            throw new InvalidArgumentException(
                'the provided data must be an array OR a \Traversable object'
            );
        }

        foreach ($rows as $row) {
            $this->insertOne($row);
        }

        return $this;
    }

    /**
     * Add a new CSV row to the generated CSV
     *
     * @param string[]|string $row a string, an array or an object implementing to '__toString' method
     *
     * @return static
     */
    public function insertOne($row)
    {
        $row = $this->formatRow($row);
        $row = $this->applyValidationRules($row);
        $csv = $this->getCsv();
        $csv->fputcsv($row, $this->delimiter, $this->enclosure);
        if ("\n" !== $this->newline) {
            $csv->fseek(-1, SEEK_CUR);
            $csv->fwrite($this->newline);
        }

        return $this;
    }

    /**
     * format the submitted data to be an array
     *
     * @param  array|string $row the row data
     *
     * @return array
     */
    protected function formatRow($row)
    {
        if (! is_array($row)) {
            return str_getcsv($row, $this->delimiter, $this->enclosure, $this->escape);
        }

        return $row;
    }

    /**
     * set the csv container as a SplFileObject instance
     * insure we use the same object for insertion to
     * avoid loosing the cursor position
     *
     * @return \SplFileObject
     */
    protected function getCsv()
    {
        if (is_null($this->csv)) {
            $this->csv = $this->getIterator();
        }

        return $this->csv;
    }

    /**
     *  {@inheritdoc}
     */
    public function isActiveStreamFilter()
    {
        return parent::isActiveStreamFilter() && is_null($this->csv);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->csv = null;
        parent::__destruct();
    }
}
