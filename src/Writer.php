<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv;

use InvalidArgumentException;
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
     * Adds multiple lines to the CSV document
     *
     * a simple wrapper method around insertOne
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
     * Adds a single line to a CSV document
     *
     * @param string[]|string $row a string, an array or an object implementing to '__toString' method
     *
     * @return static
     */
    public function insertOne($row)
    {
        if (! is_array($row)) {
            $row = str_getcsv($row, $this->delimiter, $this->enclosure, $this->escape);
        }
        $row = $this->formatRow($row);
        $this->validateRow($row);
        if (is_null($this->csv)) {
            $this->csv = $this->getIterator();
        }
        $this->csv->fputcsv($row, $this->delimiter, $this->enclosure);
        if ("\n" !== $this->newline) {
            $this->csv->fseek(-1, SEEK_CUR);
            $this->csv->fwrite($this->newline);
        }

        return $this;
    }

    /**
     *  {@inheritdoc}
     */
    public function isActiveStreamFilter()
    {
        return parent::isActiveStreamFilter() && is_null($this->csv);
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
