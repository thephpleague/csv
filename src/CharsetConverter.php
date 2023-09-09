<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv;

use OutOfRangeException;
use php_user_filter;

use function array_combine;
use function array_map;
use function in_array;
use function is_numeric;
use function mb_convert_encoding;
use function mb_list_encodings;
use function preg_match;
use function sprintf;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_filter_register;
use function stream_get_filters;
use function strtolower;
use function substr;

/**
 * Converts resource stream or tabular data content charset.
 */
class CharsetConverter extends php_user_filter
{
    public const FILTERNAME = 'convert.league.csv';
    public const BOM_SEQUENCE = 'bom_sequence';
    public const SKIP_BOM_SEQUENCE = 'skip_bom_sequence';

    protected string $input_encoding = 'UTF-8';
    protected string $output_encoding = 'UTF-8';
    protected bool $skipBomSequence =  false;

    /**
     * Static method to add the stream filter to a {@link Reader} object to handle BOM skipping.
     */
    public static function addBOMSkippingTo(Reader $document, string $output_encoding = 'UTF-8'): Reader
    {
        self::register();

        $document->addStreamFilter(
            self::getFiltername(match ($document->getInputBOM()) {
                ByteSequence::BOM_UTF16_LE => 'UTF-16LE',
                ByteSequence::BOM_UTF16_BE => 'UTF-16BE',
                ByteSequence::BOM_UTF32_LE => 'UTF-32LE',
                ByteSequence::BOM_UTF32_BE => 'UTF-32BE',
                default => 'UTF-8',
            }, $output_encoding),
            [self::BOM_SEQUENCE => self::SKIP_BOM_SEQUENCE]
        );

        return $document;
    }

    /**
     * Static method to add the stream filter to a {@link AbstractCsv} object.
     */
    public static function addTo(AbstractCsv $csv, string $input_encoding, string $output_encoding, array $params = null): AbstractCsv
    {
        self::register();

        return $csv->addStreamFilter(self::getFiltername($input_encoding, $output_encoding), $params);
    }

    /**
     * Static method to register the class as a stream filter.
     */
    public static function register(): void
    {
        $filter_name = self::FILTERNAME.'.*';
        if (!in_array($filter_name, stream_get_filters(), true)) {
            stream_filter_register($filter_name, self::class);
        }
    }

    /**
     * Static method to return the stream filter filtername.
     */
    public static function getFiltername(string $input_encoding, string $output_encoding): string
    {
        return sprintf(
            '%s.%s/%s',
            self::FILTERNAME,
            self::filterEncoding($input_encoding),
            self::filterEncoding($output_encoding)
        );
    }

    /**
     * Filter encoding charset.
     *
     * @throws OutOfRangeException if the charset is malformed or unsupported
     */
    protected static function filterEncoding(string $encoding): string
    {
        static $encoding_list;
        if (null === $encoding_list) {
            $list = mb_list_encodings();
            $encoding_list = array_combine(array_map(strtolower(...), $list), $list);
        }

        $key = strtolower($encoding);
        if (isset($encoding_list[$key])) {
            return $encoding_list[$key];
        }

        throw new OutOfRangeException('The submitted charset '.$encoding.' is not supported by the mbstring extension.');
    }

    public function onCreate(): bool
    {
        $prefix = self::FILTERNAME.'.';
        if (!str_starts_with($this->filtername, $prefix)) {
            return false;
        }

        $encodings = substr($this->filtername, strlen($prefix));
        if (1 !== preg_match(',^(?<input>[-\w]+)/(?<output>[-\w]+)$,', $encodings, $matches)) {
            return false;
        }

        try {
            $this->input_encoding = self::filterEncoding($matches['input']);
            $this->output_encoding = self::filterEncoding($matches['output']);
            $this->skipBomSequence = is_array($this->params)
                && isset($this->params[self::BOM_SEQUENCE])
                && self::SKIP_BOM_SEQUENCE === $this->params[self::BOM_SEQUENCE];
        } catch (OutOfRangeException) {
            return false;
        }

        return true;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $alreadyRun = false;
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $content = $bucket->data;
            if (!$alreadyRun && $this->skipBomSequence && null !== ($bom = Info::fetchBOMSequence($content))) {
                $content = substr($content, strlen($bom));
            }
            $alreadyRun = true;
            $bucket->data = mb_convert_encoding($content, $this->output_encoding, $this->input_encoding);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }
        restore_error_handler();

        return PSFS_PASS_ON;
    }

    /**
     * Converts Csv records collection into UTF-8.
     */
    public function convert(iterable $records): iterable
    {
        return match (true) {
            $this->output_encoding === $this->input_encoding => $records,
            is_array($records) => array_map($this, $records),
            default => new MapIterator($records, $this),
        };
    }

    /**
     * Enable using the class as a formatter for the {@link Writer}.
     */
    public function __invoke(array $record): array
    {
        $outputRecord = [];
        foreach ($record as $offset => $value) {
            [$newOffset, $newValue] = $this->encodeField($value, $offset);
            $outputRecord[$newOffset] = $newValue;
        }

        return $outputRecord;
    }

    /**
     * Walker method to convert the offset and the value of a CSV record field.
     */
    protected function encodeField(int|float|string|null $value, int|string $offset): array
    {
        if (null !== $value && !is_numeric($value)) {
            $value = mb_convert_encoding($value, $this->output_encoding, $this->input_encoding);
        }

        if (!is_numeric($offset)) {
            $offset = mb_convert_encoding($offset, $this->output_encoding, $this->input_encoding);
        }

        return [$offset, $value];
    }

    /**
     * Sets the records input encoding charset.
     */
    public function inputEncoding(string $encoding): self
    {
        $encoding = self::filterEncoding($encoding);
        if ($encoding === $this->input_encoding) {
            return $this;
        }

        $clone = clone $this;
        $clone->input_encoding = $encoding;

        return $clone;
    }

    /**
     * Sets the records output encoding charset.
     */
    public function outputEncoding(string $encoding): self
    {
        $encoding = self::filterEncoding($encoding);
        if ($encoding === $this->output_encoding) {
            return $this;
        }

        $clone = clone $this;
        $clone->output_encoding = $encoding;

        return $clone;
    }
}
