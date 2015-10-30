<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.2.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Config;

use CallbackFilterIterator;
use InvalidArgumentException;
use LimitIterator;
use SplFileObject;

/**
 *  A trait to configure and check CSV file and content
 *
 * @package League.csv
 * @since  6.0.0
 *
 */
trait Controls
{
    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character (one character only)
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * the \SplFileObject flags holder
     *
     * @var int
     */
    protected $flags;

    /**
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Sets the field delimiter
     *
     * @param string $delimiter
     *
     * @throws InvalidArgumentException If $delimiter is not a single character
     *
     * @return $this
     */
    public function setDelimiter($delimiter)
    {
        if (!$this->isValidCsvControls($delimiter)) {
            throw new InvalidArgumentException('The delimiter must be a single character');
        }
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Tell whether the submitted string is a valid CSV Control character
     *
     * @param string $str The submitted string
     *
     * @return bool
     */
    protected function isValidCsvControls($str)
    {
        return 1 == mb_strlen($str);
    }

    /**
     * Returns the current field delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * Detects the CSV file delimiters
     *
     * Returns a associative array where each key represents
     * the number of occurences and each value a delimiter with the
     * given occurence
     *
     * This method returns incorrect informations when two delimiters
     * have the same occurrence count
     *
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 7.2
     *
     * @param int      $nb_rows
     * @param string[] $delimiters additional delimiters
     *
     * @return string[]
     */
    public function detectDelimiterList($nb_rows = 1, array $delimiters = [])
    {
        $delimiters = array_merge([$this->delimiter, ',', ';', "\t"], $delimiters);
        $stats = $this->fetchDelimitersOccurrence($delimiters, $nb_rows);

        return array_flip(array_filter($stats));
    }

    /**
     * Detect Delimiters occurences in the CSV
     *
     * Returns a associative array where each key represents
     * a valid delimiter and each value the number of occurences
     *
     * @param string[] $delimiters the delimiters to consider
     * @param int      $nb_rows    Detection is made using $nb_rows of the CSV
     *
     * @throws InvalidArgumentException If $nb_rows value is invalid
     *
     * @return array
     */
    public function fetchDelimitersOccurrence(array $delimiters, $nb_rows = 1)
    {
        if (!($nb_rows = filter_var($nb_rows, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) {
            throw new InvalidArgumentException('The number of rows to consider must be a valid positive integer');
        }

        $filterRow = function ($row) {
            return is_array($row) && count($row) > 1;
        };
        $delimiters = array_unique(array_filter($delimiters, [$this, 'isValidCsvControls']));
        $csv = $this->getIterator();
        $res = [];
        foreach ($delimiters as $delim) {
            $csv->setCsvControl($delim, $this->enclosure, $this->escape);
            $iterator = new CallbackFilterIterator(new LimitIterator($csv, 0, $nb_rows), $filterRow);
            $res[$delim] = count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
        }
        arsort($res, SORT_NUMERIC);

        return $res;
    }

    /**
     * Returns the CSV Iterator
     *
     * @return SplFileObject
     */
    abstract public function getIterator();

    /**
     * Sets the field enclosure
     *
     * @param string $enclosure
     *
     * @throws InvalidArgumentException If $enclosure is not a single character
     *
     * @return $this
     */
    public function setEnclosure($enclosure)
    {
        if (!$this->isValidCsvControls($enclosure)) {
            throw new InvalidArgumentException('The enclosure must be a single character');
        }
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * Returns the current field enclosure
     *
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * Sets the field escape character
     *
     * @param string $escape
     *
     * @throws InvalidArgumentException If $escape is not a single character
     *
     * @return $this
     */
    public function setEscape($escape)
    {
        if (!$this->isValidCsvControls($escape)) {
            throw new InvalidArgumentException('The escape character must be a single character');
        }
        $this->escape = $escape;

        return $this;
    }

    /**
     * Returns the current field escape character
     *
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * Sets the Flags associated to the CSV SplFileObject
     *
     * @param int $flags
     *
     * @throws InvalidArgumentException If the argument is not a valid integer
     *
     * @return $this
     */
    public function setFlags($flags)
    {
        $flags = $this->filterInteger($flags, 0, 'you should use a `SplFileObject` Constant');
        $this->flags = $flags | SplFileObject::READ_CSV;

        return $this;
    }

    /**
     * @inheritdoc
     */
    abstract protected function filterInteger($int, $minValue, $errorMessage);

    /**
     * Returns the file Flags
     *
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }


    /**
     * Sets the newline sequence characters
     *
     * @param string $newline
     *
     * @return static
     */
    public function setNewline($newline)
    {
        $this->newline = (string) $newline;

        return $this;
    }

    /**
     * Returns the current newline sequence characters
     *
     * @return string
     */
    public function getNewline()
    {
        return $this->newline;
    }
}
