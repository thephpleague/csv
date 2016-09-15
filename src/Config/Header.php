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

use InvalidArgumentException;
use League\Csv\AbstractCsv;
use LimitIterator;

/**
 * Trait to configure the CSV header
 *
 * @package League.csv
 * @since  9.0.0
 */
trait Header
{
    /**
     * Csv Header Info
     *
     * @var array|int
     */
    protected $header = [];

    /**
     * Returns the inner SplFileObject
     *
     * @return SplFileObject
     */
    abstract public function getIterator();

    /**
     * Returns the input BOM sequence
     *
     * @return string
     */
    abstract public function getInputBOM();

    /**
     * Returns the field enclosure
     *
     * @return string
     */
    abstract public function getEnclosure();

    /**
     * Strip the BOM character from the record
     *
     * @param string[] $record
     * @param string   $bom
     * @param string   $enclosure
     *
     * @return string[]
     */
    abstract protected function stripBOM(array $record, $bom, $enclosure);

    /**
     * Returns the input encoding charset
     *
     * @return string
     */
    abstract public function getInputEncoding();

    /**
     * Convert a CSV record to UTF-8
     *
     * @param string[] $record
     * @param string   $input_encoding
     *
     * @return string[]
     */
    abstract protected function convertRecordToUtf8(array $record, $input_encoding);

    /**
     * Filter the header content
     *
     * @param string[] $header
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return string[]
     */
    abstract protected function filterHeader(array $header);

    /**
     * Tell whether to use Stream Filter or not to convert the CSV
     *
     * @param AbstractCsv $csv
     *
     * @return bool
     */
    abstract protected function useInternalConverter(AbstractCsv $csv);

    /**
     * Validate an integer
     *
     * @param int    $int
     * @param int    $min_value
     * @param string $error_message
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    abstract protected function filterInteger($int, $min_value, $error_message);

    /**
     * Returns the record offset used as header
     *
     * If no CSV record is used this method MUST return null
     *
     * @return int|null
     */
    public function getHeaderOffset()
    {
        if (is_array($this->header)) {
            return null;
        }

        return $this->header;
    }

    /**
     * Returns the CSV header
     *
     * @return string[]
     */
    public function getHeader()
    {
        if (is_array($this->header)) {
            return $this->header;
        }

        return $this->getHeaderFromDocument();
    }

    /**
     * Get the Header from a CSV record
     *
     * @throws InvalidArgumentException if the offset does not exist
     *
     * @return string[]
     */
    protected function getHeaderFromDocument()
    {
        $iterator = new LimitIterator($this->getIterator(), $this->header);
        $iterator->rewind();
        $header = $iterator->current();
        if ($iterator->key() !== $this->header) {
            throw new InvalidArgumentException('the select offset does not exist');
        }

        if (0 !== $this->header) {
            return $header;
        }

        return $this->formatHeader($header, $this->getInputBOM(), $this->getEnclosure());
    }

    /**
     * Format the Header
     *
     * @param string[] $header    The header
     * @param string   $bom       The BOM sequence
     * @param string   $enclosure The enclosure sequence
     *
     * @return string[]
     */
    protected function formatHeader(array $header, $bom, $enclosure)
    {
        $header = $this->stripBOM($header, $bom, $enclosure);
        if (!$this->useInternalConverter($this)) {
            return $header;
        }

        return $this->convertRecordToUtf8($header, $this->getInputEncoding());
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
    public function setHeader($offset_or_keys)
    {
        if (is_array($offset_or_keys)) {
            $this->header = $this->filterHeader($offset_or_keys);
            return $this;
        }

        if (null === $offset_or_keys) {
            $this->header = [];
            return $this;
        }

        $this->header = $this->filterInteger($offset_or_keys, 0, 'the header offset is invalid');
        return $this;
    }
}
