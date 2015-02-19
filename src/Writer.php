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
use League\Csv\Exception\InvalidRowException;
use League\Csv\Modifier;
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
     * Row Formatter and Validator trait
     */
    use Modifier\RowFilter;

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
        $this->validateRow($row);
        $csv = $this->getCsv();
        $csv->fputcsv($row, $this->delimiter, $this->enclosure);
        if ("\n" !== $this->newline) {
            $csv->fseek(-1, SEEK_CUR);
            $csv->fwrite($this->newline);
        }

        return $this;
    }

    /**
     * Format the given row
     *
     * @param array|string $row
     *
     * @return array
     */
    protected function formatRow($row)
    {
        if (! is_array($row)) {
            $row = str_getcsv($row, $this->delimiter, $this->enclosure, $this->escape);
        }

        foreach ($this->formatters as $formatter) {
            $row = $formatter($row);
        }

        return $row;
    }

    /**
    * validate a row
    *
    * @param array $row
    *
    * @throws \League\Csv\Exception\InvalidRowException If the validation failed
    *
    * @return void
    */
    protected function validateRow(array $row)
    {
        foreach ($this->validators as $name => $validator) {
            if (true !== $validator($row)) {
                throw new InvalidRowException($name, $row, 'row validation failed');
            }
        }
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
