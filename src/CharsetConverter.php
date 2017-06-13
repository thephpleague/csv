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

use ArrayIterator;
use Iterator;
use League\Csv\Exception\OutOfRangeException;
use php_user_filter;
use Throwable;
use Traversable;

/**
 *  A class to convert your CSV records collection charset
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class CharsetConverter extends php_user_filter
{
    use ValidatorTrait;

    const FILTERNAME = 'convert.league.csv';

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
     * Static method to add the stream filter to a {@link AbstractCsv} object
     *
     * @param AbstractCsv $csv
     * @param string      $input_encoding
     * @param string      $output_encoding
     */
    public static function addTo(AbstractCsv $csv, string $input_encoding, string $output_encoding)
    {
        $filtername = self::FILTERNAME.'.*';
        if (!in_array($filtername, stream_get_filters())) {
            stream_filter_register($filtername, __CLASS__);
        }

        $csv->addStreamFilter(sprintf(
            '%s.%s/%s',
            self::FILTERNAME,
            self::filterEncoding($input_encoding),
            self::filterEncoding($output_encoding)
        ));
    }

    /**
     * Filter encoding charset
     *
     * @param string $encoding
     *
     * @throws OutOfRangeException if the charset is malformed or unsupported
     *
     * @return string
     */
    protected static function filterEncoding(string $encoding): string
    {
        static $encoding_list;
        if (null === $encoding_list) {
            $list = mb_list_encodings();
            $encoding_list = array_combine(array_map('strtolower', $list), $list);
        }

        $key = strtolower($encoding);
        if (isset($encoding_list[$key])) {
            return $encoding_list[$key];
        }

        throw new OutOfRangeException(sprintf('The submitted charset %s is not supported by the mbstring extension', $encoding));
    }

    /**
     * @inheritdoc
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = @mb_convert_encoding($res->data, $this->output_encoding, $this->input_encoding);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }

    /**
     * @inheritdoc
     */
    public function onCreate()
    {
        $prefix = self::FILTERNAME.'.';
        if (0 !== strpos($this->filtername, $prefix)) {
            return false;
        }

        $encodings = substr($this->filtername, strlen($prefix));
        if (!preg_match(',^(?<input>[-\w]+)\/(?<output>[-\w]+)$,', $encodings, $matches)) {
            return false;
        }

        try {
            $this->input_encoding = $this->filterEncoding($matches['input']);
            $this->output_encoding = $this->filterEncoding($matches['output']);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Enable using the class as a formatter for the {@link Writer}
     *
     * @param array $record CSV record
     *
     * @return string[]
     */
    public function __invoke(array $record): array
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
     * @return Iterator
     */
    public function convert($records): Iterator
    {
        $records = $this->filterIterable($records, __METHOD__);
        if (is_array($records)) {
            $records = new ArrayIterator($records);
        }

        if ($this->output_encoding === $this->input_encoding) {
            return $records;
        }

        $convert = function (array $record): array {
            array_walk($record, [$this, 'encodeField']);
            return $record;
        };

        return new MapIterator($records, $convert);
    }

    /**
     * Sets the records input encoding charset
     *
     * @param string $encoding
     *
     * @return self
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
     * @return self
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
