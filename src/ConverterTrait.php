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

use Iterator;
use League\Csv\Exception\InvalidArgumentException;
use Traversable;

/**
 *  A trait to ease records conversion
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
trait ConverterTrait
{
    use ValidatorTrait;

    /**
     * Charset Encoding for the CSV
     *
     * This information is used when converting the CSV to XML or JSON
     *
     * @var string
     */
    protected $input_encoding = 'UTF-8';

    /**
     * Sets the CSV encoding charset
     *
     * @param string $input_encoding
     *
     * @throws InvalidArgumentException if the charset is empty
     *
     * @return static
     */
    public function inputEncoding(string $input_encoding): self
    {
        $input_encoding = strtoupper(str_replace('_', '-', $input_encoding));
        $test = filter_var($input_encoding, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if ($test === $input_encoding && $input_encoding != '') {
            $clone = clone $this;
            $clone->input_encoding = $input_encoding;

            return $clone;
        }

        throw new InvalidArgumentException('Invalid Character Error');
    }

    /**
     * Convert Csv file into UTF-8
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return array|Iterator
     */
    protected function convertToUtf8($records)
    {
        if (stripos($this->input_encoding, 'UTF-8') !== false) {
            return $records;
        }

        $walker = function (&$value, &$offset) {
            $value = mb_convert_encoding((string) $value, 'UTF-8', $this->input_encoding);
            $offset = mb_convert_encoding((string) $offset, 'UTF-8', $this->input_encoding);
        };

        $convert = function (array $record) use ($walker): array {
            array_walk($record, $walker);
            return $record;
        };

        return is_array($records) ? array_map($convert, $records) : new MapIterator($records, $convert);
    }
}
