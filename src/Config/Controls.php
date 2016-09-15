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
namespace League\Csv\Config;

use CallbackFilterIterator;
use InvalidArgumentException;
use League\Csv\AbstractCsv;
use LimitIterator;
use SplFileObject;

/**
 * Trait to configure CSV document properties
 *
 * @package League.csv
 * @since  6.0.0
 */
trait Controls
{
    use Validator;

    use StreamFilter;

    use Header;

    /**
     * the field delimiter character
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * the field enclosure character
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * the field escape character
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * The input CSV BOM sequence
     *
     * @var string
     */
    protected $input_bom;

    /**
     * The output CSV BOM sequence
     *
     * @var string
     */
    protected $output_bom = '';

    /**
     * Returns the inner SplFileObject
     *
     * @return SplFileObject
     */
    abstract public function getIterator();

    /**
     * Returns the field delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * Returns the field enclosure
     *
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * Returns the field escape character
     *
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * Returns the newline sequence
     *
     * @return string
     */
    public function getNewline()
    {
        return $this->newline;
    }

    /**
     * Returns the input BOM sequence
     *
     * @return string
     */
    public function getInputBOM()
    {
        if (null === $this->input_bom) {
            $bom = [
                AbstractCsv::BOM_UTF32_BE, AbstractCsv::BOM_UTF32_LE,
                AbstractCsv::BOM_UTF16_BE, AbstractCsv::BOM_UTF16_LE, AbstractCsv::BOM_UTF8,
            ];
            $csv = $this->getIterator();
            $csv->setFlags(SplFileObject::READ_CSV);
            $csv->rewind();
            $line = $csv->fgets();
            $res = array_filter($bom, function ($sequence) use ($line) {
                return strpos($line, $sequence) === 0;
            });

            $this->input_bom = (string) array_shift($res);
        }

        return $this->input_bom;
    }

    /**
     * Returns the BOM sequence in use on output methods
     *
     * @return string
     */
    public function getOutputBOM()
    {
        return $this->output_bom;
    }

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
        $this->delimiter = $this->filterCsvControl($delimiter);

        return $this;
    }

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
        $this->enclosure = $this->filterCsvControl($enclosure);

        return $this;
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
        $this->escape = $this->filterCsvControl($escape);

        return $this;
    }

    /**
     * Sets the newline sequence
     *
     * @param string $newline
     *
     * @return $this
     */
    public function setNewline($newline)
    {
        $this->newline = (string) $newline;

        return $this;
    }

    /**
     * Sets the BOM sequence to use in output method
     *
     * @param string $str The BOM sequence
     *
     * @return $this
     */
    public function setOutputBOM($str)
    {
        if (empty($str)) {
            $this->output_bom = '';

            return $this;
        }

        $this->output_bom = (string) $str;

        return $this;
    }

    /**
     * Detect Delimiters occurences in the CSV Document
     *
     * Returns a associative array where each key represents
     * a valid delimiter and each value the number of occurences
     *
     * @param string[] $delimiters the delimiters to consider
     * @param int      $nb_rows    Detection is made using $nb_rows of the CSV
     *
     * @return array
     */
    public function fetchDelimitersOccurrence(array $delimiters, $nb_rows = 1)
    {
        $nb_rows = $this->filterInteger($nb_rows, 1, 'The number of rows to consider must be a valid positive integer');
        $filter_row = function ($row) {
            return is_array($row) && count($row) > 1;
        };
        $delimiters = array_unique(array_filter($delimiters, [$this, 'isValidCsvControls']));
        $csv = $this->getIterator();
        $res = [];
        foreach ($delimiters as $delim) {
            $csv->setCsvControl($delim, $this->enclosure, $this->escape);
            $iterator = new CallbackFilterIterator(new LimitIterator($csv, 0, $nb_rows), $filter_row);
            $res[$delim] = count(iterator_to_array($iterator, false), COUNT_RECURSIVE);
        }
        arsort($res, SORT_NUMERIC);

        return $res;
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
        try {
            return $this->filterCsvControl($str);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Outputs all data from the CSV document
     *
     * if a filename is provide output sends an HTTP Response containing a downloadable CSV
     *
     * @param string $filename CSV downloaded name if present adds extra headers
     *
     * @return int the number of bytes sent
     */
    public function output($filename = null)
    {
        if (null !== $filename) {
            $filename = filter_var($filename, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            header('Content-Type: text/csv');
            header('Content-Transfer-Encoding: binary');
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }

        return $this->fpassthru();
    }

    /**
     * Outputs all data from the CSV
     *
     * @return int Returns the number of bytes read.
     */
    protected function fpassthru()
    {
        $bom = '';
        $input_bom = $this->getInputBOM();
        if ($this->output_bom && $input_bom != $this->output_bom) {
            $bom = $this->output_bom;
        }
        $csv = $this->getIterator();
        $csv->setFlags(SplFileObject::READ_CSV);
        $csv->rewind();
        if (!empty($bom)) {
            $csv->fseek(mb_strlen($input_bom));
        }
        echo $bom;
        $res = $csv->fpassthru();

        return $res + strlen($bom);
    }

    /**
     * Returns a string representation of the CSV Document
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();
        $this->fpassthru();

        return ob_get_clean();
    }
}
