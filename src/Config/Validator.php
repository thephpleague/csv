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

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use League\Csv\AbstractCsv;

/**
 *  Trait to validate CSV content and properties
 *
 * @package League.csv
 * @since  9.0.0
 *
 */
trait Validator
{
    /**
     * record name for XML conversion
     *
     * @var string
     */
    protected $row_name;

    /**
     * Cell name for XML conversion
     *
     * @var string
     */
    protected $cell_name;

    /**
     * Convert a row into a DOMElement
     *
     * @param string[]       $record Csv record
     * @param DOMDocument $doc
     *
     * @return DOMElement
     */
    protected function convertRecordToDOMNode(array $record, DOMDocument $doc)
    {
        $node = $doc->createElement($this->row_name);
        foreach ($record as $value) {
            $cell = $doc->createElement($this->cell_name);
            $cell->appendChild($doc->createTextNode($value));
            $node->appendChild($cell);
        }

        return $node;
    }

    /**
     * Convert a CSV record to UTF-8
     *
     * @param string[]  $record
     * @param string $input_encoding
     *
     * @return string[]
     */
    protected function convertRecordToUtf8(array $record, $input_encoding)
    {
        $convert = function ($value) use ($input_encoding) {
            return iconv($input_encoding, 'UTF-8//TRANSLIT', $value);
        };

        return array_map($convert, $record);
    }

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
    protected function validateInteger($int, $min_value, $error_message)
    {
        $int = filter_var($int, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min_value]]);
        if (false !== $int) {
            return $int;
        }

        throw new InvalidArgumentException($error_message);
    }

    /**
     * validate a string
     *
     * @param mixed $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     * @return string
     */
    protected static function validateString($str)
    {
        if (is_string($str) || (is_object($str) && method_exists($str, '__toString'))) {
            return (string) $str;
        }
        throw new InvalidArgumentException('Expected data must be a string or stringable');
    }

    /**
     * Tell whether to use Stream Filter or not to convert the CSV
     *
     * @return bool
     */
    protected function useInternalConverter(AbstractCsv $csv)
    {
        return !('UTF-8' === $csv->getInputEncoding()
            || ($csv->isActiveStreamFilter() && STREAM_FILTER_READ === $csv->getStreamFilterMode()));
    }

    /**
     * Strip the BOM character from the record
     *
     * @param string[]  $record
     * @param string $bom
     * @param string $enclosure
     *
     * @return string[]
     */
    protected function stripBOM(array $record, $bom, $enclosure)
    {
        $bom_length = mb_strlen($bom);
        if (0 == $bom_length) {
            return $record;
        }

        $record[0] = mb_substr($record[0], $bom_length);
        if ($record[0][0] === $enclosure && mb_substr($record[0], -1, 1) === $enclosure) {
            $record[0] = mb_substr($record[0], 1, -1);
        }

        return $record;
    }

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param string[] $header
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return string[]
     */
    protected function validateHeader(array $header)
    {
        if (empty($header)) {
            return $header;
        }

        foreach ($header as &$value) {
            $value = $this->validateString($value);
        }
        unset($value);

        if (count(array_unique($header)) == count($header)) {
            return $header;
        }

        throw new InvalidArgumentException('Use a flat array with unique string values');
    }
}
