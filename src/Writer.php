<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.1.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use InvalidArgumentException;
use League\Csv\Exception\InvalidRowException;
use ReflectionMethod;
use SplFileObject;
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
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * The CSV object holder
     *
     * @var SplFileObject
     */
    protected $csv;

    /**
     * fputcsv method from SplFileObject
     *
     * @var ReflectionMethod
     */
    protected static $fputcsv;

    /**
     * Nb parameters for SplFileObject::fputcsv method
     *
     * @var integer
     */
    protected static $fputcsv_param_count;

    /**
     * Callables to validate the row before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * Callables to format the row before insertion
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * @inheritdoc
     */
    protected function __construct($path, $open_mode = 'r+')
    {
        parent::__construct($path, $open_mode);
        static::initFputcsv();
    }

    /**
     * initiate a SplFileObject::fputcsv method
     */
    protected static function initFputcsv()
    {
        if (null === static::$fputcsv) {
            static::$fputcsv = new ReflectionMethod('\SplFileObject', 'fputcsv');
            static::$fputcsv_param_count = static::$fputcsv->getNumberOfParameters();
        }
    }

    /**
     *  {@inheritdoc}
     */
    public function __destruct()
    {
        $this->csv = null;
        parent::__destruct();
    }

    /**
     * add a formatter to the collection
     *
     * @param callable $callable
     *
     * @return static
     */
    public function addFormatter(callable $callable)
    {
        $this->formatters[] = $callable;

        return $this;
    }

    /**
     * add a Validator to the collection
     *
     * @param callable $callable
     * @param string   $name     the rule name
     *
     * @return static
     */
    public function addValidator(callable $callable, $name)
    {
        $this->validators[$this->validateString($name)] = $callable;

        return $this;
    }

    /**
     * Adds multiple lines to the CSV document
     *
     * a simple wrapper method around insertOne
     *
     * @param Traversable|array $rows a multidimensional array or a Traversable object
     *
     * @throws InvalidArgumentException If the given rows format is invalid
     *
     * @return static
     */
    public function insertAll($rows)
    {
        if (!is_array($rows) && !$rows instanceof Traversable) {
            throw new InvalidArgumentException('the provided data must be an iterable');
        }

        foreach ($rows as $row) {
            $this->insertOne($row);
        }

        return $this;
    }

    /**
     * Adds a single line to a CSV document
     *
     * @param string[] $row a string, an array or an object implementing to '__toString' method
     *
     * @return static
     */
    public function insertOne(array $row)
    {
        $row = $this->formatRow($row);
        $this->validateRow($row);
        if (null === $this->csv) {
            $this->csv = $this->getIterator();
        }

        static::$fputcsv->invokeArgs($this->csv, $this->getFputcsvParameters($row));
        if ("\n" !== $this->newline) {
            $this->csv->fseek(-1, SEEK_CUR);
            $this->csv->fwrite($this->newline);
        }

        return $this;
    }

    /**
     * Format the given row
     *
     * @param array $row
     *
     * @return array
     */
    protected function formatRow(array $row)
    {
        foreach ($this->formatters as $formatter) {
            $row = call_user_func($formatter, $row);
        }

        return $row;
    }

    /**
    * Validate a row
    *
    * @param array $row
    *
    * @throws InvalidRowException If the validation failed
    */
    protected function validateRow(array $row)
    {
        foreach ($this->validators as $name => $validator) {
            if (true !== call_user_func($validator, $row)) {
                throw new InvalidRowException($name, $row, 'row validation failed');
            }
        }
    }

    /**
     * returns the parameters for SplFileObject::fputcsv
     *
     * @param array $fields The fields to be add
     *
     * @return array
     */
    protected function getFputcsvParameters(array $fields)
    {
        $parameters = [$fields, $this->delimiter, $this->enclosure];
        if (4 == static::$fputcsv_param_count) {
            $parameters[] = $this->escape;
        }

        return $parameters;
    }

    /**
     *  {@inheritdoc}
     */
    public function isActiveStreamFilter()
    {
        return parent::isActiveStreamFilter() && null === $this->csv;
    }
}
