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
use php_user_filter;
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

    const STREAM_FILTERNAME = 'convert.league.csv';

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
     * Static method to register the class as a PHP stream filter
     *
     * @return bool
     */
    public static function registerStreamFilter(): bool
    {
        return stream_filter_register(self::STREAM_FILTERNAME.'.*', CharsetConverter::class);
    }

    /**
     * Static method to format the filtername to be used with stream_filter_append
     *
     * @param string $input_encoding
     * @param string $output_encoding
     *
     * @return string
     */
    public static function getFiltername(string $input_encoding, string $output_encoding): string
    {
        return sprintf(
            '%s.%s/%s',
            self::STREAM_FILTERNAME,
            self::filterEncoding($input_encoding),
            self::filterEncoding($output_encoding)
        );
    }

    /**
     * @inherit
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
     * @inherit
     */
    public function onCreate()
    {
        $params = substr($this->filtername, strlen(self::STREAM_FILTERNAME) + 1);
        if (!preg_match(',^(?<input>[-\w]+)\/(?<output>[-\w]+)$,', $params, $matches)) {
            return false;
        }

        $this->input_encoding = $this->filterEncoding($matches['input']);
        $this->output_encoding = $this->filterEncoding($matches['output']);
        return true;
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
     * @return array|Iterator
     */
    public function convert($records)
    {
        $records = $this->filterIterable($records, __METHOD__);
        if ($this->output_encoding === $this->input_encoding) {
            return $records;
        }

        $convert = function (array $record): array {
            array_walk($record, [$this, 'encodeField']);
            return $record;
        };

        if (is_array($records)) {
            return array_map($convert, $records);
        }

        return new MapIterator($records, $convert);
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
