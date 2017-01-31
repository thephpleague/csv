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

use InvalidArgumentException;
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
    protected $stream_filter_mode = STREAM_FILTER_WRITE;

    /**
     * The CSV object holder
     *
     * @var SplFileObject|StreamIterator
     */
    protected $csv;

    /**
     * fputcsv method from SplFileObject or StreamIterator
     *
     * @var ReflectionMethod
     */
    protected $fputcsv;

    /**
     * Nb parameters for SplFileObject::fputcsv method
     *
     * @var integer
     */
    protected $fputcsv_param_count;

    /**
     * add a formatter to the collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFormatter(callable $callable): self
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
     * @return $this
     */
    public function addValidator(callable $callable, string $name): self
    {
        $this->validators[$name] = $callable;

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
    public function insertAll($rows): self
    {
        if (!is_array($rows) && !$rows instanceof Traversable) {
            throw new InvalidArgumentException(
                'the provided data must be an array OR a `Traversable` object'
            );
        }

        foreach ($rows as $row) {
            $this->insertOne($row);
        }

        return $this;
    }

    /**
     * Adds a single line to a CSV document
     *
     * @param string[]|string $row a string, an array or an object implementing to '__toString' method
     *
     * @return static
     */
    public function insertOne(array $row): self
    {
        $row = $this->formatRow($row);
        $this->validateRow($row);
        $this->addRow($row);

        return $this;
    }

    /**
     * Format the given row
     *
     * @param array $row
     *
     * @return array
     */
    protected function formatRow(array $row): array
    {
        foreach ($this->formatters as $formatter) {
            $row = ($formatter)($row);
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
            if (true !== ($validator)($row)) {
                throw new InvalidRowException($name, $row, 'row validation failed');
            }
        }
    }

    /**
     * Add new record to the CSV document
     *
     * @param array $row record to add
     */
    protected function addRow(array $row)
    {
        $this->initCsv();
        $this->fputcsv->invokeArgs($this->csv, $this->getFputcsvParameters($row));
        if ("\n" !== $this->newline) {
            $this->csv->fseek(-1, SEEK_CUR);
            $this->csv->fwrite($this->newline, strlen($this->newline));
        }
    }

    /**
     * Initialize the CSV object and settings
     */
    protected function initCsv()
    {
        if (null !== $this->csv) {
            return;
        }

        $this->csv = $this->getCsvDocument();
        $this->fputcsv = new ReflectionMethod(get_class($this->csv), 'fputcsv');
        $this->fputcsv_param_count = $this->fputcsv->getNumberOfParameters();
    }

    /**
     * returns the parameters for SplFileObject::fputcsv
     *
     * @param array $fields The fields to be add
     *
     * @return array
     */
    protected function getFputcsvParameters(array $fields): array
    {
        $parameters = [$fields, $this->delimiter, $this->enclosure];
        if (4 == $this->fputcsv_param_count) {
            $parameters[] = $this->escape;
        }

        return $parameters;
    }

    /**
     *  {@inheritdoc}
     */
    public function isActiveStreamFilter(): bool
    {
        return parent::isActiveStreamFilter() && null === $this->csv;
    }

    /**
     *  {@inheritdoc}
     */
    public function __destruct()
    {
        $this->csv = null;
        parent::__destruct();
    }
}
