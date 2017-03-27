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
use Traversable;

/**
 *  A class to encode your CSV records collection
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class Encoder
{
    use ValidatorTrait;

    /**
     * The records input encoding charset
     *
     * @var string
     */
    protected $input_encoding = 'UTF-8';

    /**
     * The records output encoding charset
     *
     * @var string
     */
    protected $output_encoding = 'UTF-8';

    /**
     * Enable using the class as a formatter for the {@link Writer}
     *
     * @see encodeOne
     *
     * @param array $record CSV record
     *
     * @return string[]
     */
    public function __invoke(array $record): array
    {
        return $this->encodeOne($record);
    }

    /**
     * Encode a CSV record
     *
     * @param array $record CSV record
     *
     * @return string[]
     */
    public function encodeOne(array $record): array
    {
        if ($this->output_encoding !== $this->input_encoding) {
            array_walk($record, [$this, 'encodeField']);
        }

        return $record;
    }

    /**
     * Walker method to convert the offset and the value of a CSV record field
     *
     * @param string|null &$value
     * @param string|int  &$offset
     */
    protected function encodeField(&$value, &$offset)
    {
        if (null !== $value) {
            $value = mb_convert_encoding((string) $value, $this->output_encoding, $this->input_encoding);
        }

        if (!is_int($offset)) {
            $offset = mb_convert_encoding((string) $offset, $this->output_encoding, $this->input_encoding);
        }
    }

    /**
     * Convert Csv file into UTF-8
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return array|Iterator
     */
    public function encodeAll($records)
    {
        $records = $this->filterIterable($records, __METHOD__);
        if ($this->output_encoding === $this->input_encoding) {
            return $records;
        }

        $convert = function (array $record): array {
            array_walk($record, [$this, 'encodeField']);
            return $record;
        };

        return is_array($records) ? array_map($convert, $records) : new MapIterator($records, $convert);
    }

    /**
     * Sets the records input encoding charset
     *
     * @param string $encoding
     *
     * @return static
     */
    public function inputEncoding(string $encoding): self
    {
        $encoding = $this->filterEncoding($encoding);
        if ($encoding === $this->input_encoding) {
            return $this;
        }

        $clone = clone $this;
        $clone->input_encoding = $encoding;

        return $clone;
    }

    /**
     * Sets the records output encoding charset
     *
     * @param string $encoding
     *
     * @return static
     */
    public function outputEncoding(string $encoding): self
    {
        $encoding = $this->filterEncoding($encoding);
        if ($encoding === $this->output_encoding) {
            return $this;
        }

        $clone = clone $this;
        $clone->output_encoding = $encoding;

        return $clone;
    }
}
