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

namespace League\Csv\Config;

use CallbackFilterIterator;
use InvalidArgumentException;
use League\Csv\AbstractCsv;
use LimitIterator;
use SplFileObject;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  9.0.0
 * @internal
 */
trait ControlsTrait
{
    use ValidatorTrait;

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
     * newline character
     *
     * @var string
     */
    protected $newline = "\n";

    /**
     * Charset Encoding for the CSV
     *
     * @var string
     */
    protected $input_encoding = 'UTF-8';

    /**
     * The Input file BOM character
     * @var string
     */
    protected $input_bom;

    /**
     * The Output file BOM character
     * @var string
     */
    protected $output_bom = '';

    /**
     * CSV Document header offset
     *
     * @var int|null
     */
    protected $header_offset;

    /**
     * CSV Document header
     *
     * @var string[]
     */
    protected $header = [];

    /**
     * Returns the inner CSV Document Iterator object
     *
     * @return StreamIterator|SplFileObject
     */
    abstract public function getCsvDocument();

    /**
     * Returns the current field delimiter
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Returns the current field enclosure
     *
     * @return string
     */
    public function getEnclosure(): string
    {
        return $this->enclosure;
    }

    /**
     * Returns the current field escape character
     *
     * @return string
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * Returns the current newline sequence characters
     *
     * @return string
     */
    public function getNewline(): string
    {
        return $this->newline;
    }

    /**
     * Gets the source CSV encoding charset
     *
     * @return string
     */
    public function getInputEncoding(): string
    {
        return $this->input_encoding;
    }

    /**
     * Returns the BOM sequence in use on Output methods
     *
     * @return string
     */
    public function getOutputBOM(): string
    {
        return $this->output_bom;
    }

    /**
     * Returns the BOM sequence of the given CSV
     *
     * @return string
     */
    public function getInputBOM(): string
    {
        if (null === $this->input_bom) {
            $bom = [
                AbstractCsv::BOM_UTF32_BE, AbstractCsv::BOM_UTF32_LE,
                AbstractCsv::BOM_UTF16_BE, AbstractCsv::BOM_UTF16_LE, AbstractCsv::BOM_UTF8,
            ];
            $csv = $this->getCsvDocument();
            $csv->setFlags(SplFileObject::READ_CSV);
            $csv->rewind();
            $line = $csv->fgets();
            $res  = array_filter($bom, function ($sequence) use ($line) {
                return strpos($line, $sequence) === 0;
            });

            $this->input_bom = (string) array_shift($res);
        }

        return $this->input_bom;
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
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $this->filterControl($delimiter, 'delimiter');

        return $this;
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
     * @return array
     */
    public function fetchDelimitersOccurrence(array $delimiters, int $nb_rows = 1): array
    {
        $nb_rows = $this->filterInteger($nb_rows, 1, 'The number of rows to consider must be a valid positive integer');
        $filter_row = function ($row) {
            return is_array($row) && count($row) > 1;
        };
        $delimiters = array_unique(array_filter($delimiters, function ($value) {
            return 1 == strlen($value);
        }));
        $csv = $this->getCsvDocument();
        $csv->setFlags(SplFileObject::READ_CSV);
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
     * Sets the field enclosure
     *
     * @param string $enclosure
     *
     * @throws InvalidArgumentException If $enclosure is not a single character
     *
     * @return $this
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $this->filterControl($enclosure, 'enclosure');

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
    public function setEscape(string $escape): self
    {
        $this->escape = $this->filterControl($escape, 'escape');

        return $this;
    }

    /**
     * Sets the newline sequence characters
     *
     * @param string $newline
     *
     * @return static
     */
    public function setNewline(string $newline): self
    {
        $this->newline = (string) $newline;

        return $this;
    }

    /**
     * Sets the CSV encoding charset
     *
     * @param string $str
     *
     * @return static
     */
    public function setInputEncoding(string $str): self
    {
        $str = str_replace('_', '-', $str);
        $str = filter_var($str, FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        if (empty($str)) {
            throw new InvalidArgumentException('you should use a valid charset');
        }
        $this->input_encoding = strtoupper($str);

        return $this;
    }

    /**
     * Sets the BOM sequence to prepend the CSV on output
     *
     * @param string $str The BOM sequence
     *
     * @return static
     */
    public function setOutputBOM(string $str): self
    {
        if (empty($str)) {
            $this->output_bom = '';

            return $this;
        }

        $this->output_bom = (string) $str;

        return $this;
    }

    /**
     * Returns the record offset used as header
     *
     * If no CSV record is used this method MUST return null
     *
     * @return int|null
     */
    public function getHeaderOffset()
    {
        return $this->header_offset;
    }

    /**
     * Returns the header
     *
     * If no CSV record is used this method MUST return an empty array
     *
     * @return string[]
     */
    public function getHeader(): array
    {
        if (null !== $this->header_offset) {
            $this->header = $this->filterHeader($this->getRow($this->header_offset));
        }

        return $this->header;
    }

    /**
     * Returns a single row from the CSV without filtering
     *
     * @param int $offset
     *
     * @throws InvalidArgumentException If the $offset is not valid or the row does not exist
     *
     * @return array
     */
    abstract protected function getRow(int $offset): array;

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return array
     */
    protected function filterHeader(array $keys): array
    {
        if (empty($keys)) {
            return $keys;
        }

        if ($keys !== array_unique(array_filter($keys, [$this, 'isValidKey']))) {
            throw new InvalidArgumentException('Use a flat array with unique string values');
        }

        return $keys;
    }

    /**
     * Selects the array to be used as key for the fetchAssoc method
     *
     * Because of the header is represented as an array, to be valid
     * a header MUST contain only unique string value.
     *
     * <ul>
     * <li>If a array is given it will be used as the header</li>
     * <li>If a integer is given it will represent the offset of the record to be used as header</li>
     * <li>If an empty array or null is given it will mean that no header is used</li>
     * </ul>
     *
     * @param int|null|string[] $offset_or_keys the assoc key OR the row Index to be used
     *                                          as the key index
     *
     * @return $this
     */
    public function setHeader($offset_or_keys): self
    {
        if (is_array($offset_or_keys)) {
            $this->header = $this->filterHeader($offset_or_keys);
            $this->header_offset = null;

            return $this;
        }

        if (null === $offset_or_keys) {
            $this->header = [];
            $this->header_offset = null;

            return $this;
        }

        $this->header_offset = $this->filterInteger(
            $offset_or_keys,
            0,
            'the row index must be a positive integer, 0 or a non empty array'
        );

        return $this;
    }

    /**
     * Returns whether the submitted value can be used as string
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isValidKey($value): bool
    {
        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
    }
}
