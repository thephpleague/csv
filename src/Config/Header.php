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
use LimitIterator;

/**
 * Trait to configure the CSV header
 *
 * @package League.csv
 * @since  9.0.0
 *
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
     * Tell whether the current header is internal
     * or user submitted
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
     * @return array
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
     * @return array
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
     * Format the Document Header
     *
     * @param string[] $header
     * @param string   $bom
     * @param string   $enclosure
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
     * @param int|null|array $offset_or_keys the assoc key OR the row Index to be used
     *                                       as the key index
     *
     * @return $this
     */
    public function setHeader($offset_or_keys)
    {
        if (is_array($offset_or_keys)) {
            $this->header = $this->validateHeader($offset_or_keys);
            return $this;
        }

        if (null === $offset_or_keys) {
            $this->header = [];
            return $this;
        }

        $this->header = $this->validateInteger($offset_or_keys, 0, 'the header offset is invalid');
        return $this;
    }
}
