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

use Deprecated;
use OutOfRangeException;
use php_user_filter;
use RuntimeException;
use Throwable;
use TypeError;

use function array_map;
use function array_reduce;
use function get_resource_type;
use function gettype;
use function in_array;
use function is_numeric;
use function is_resource;
use function mb_convert_encoding;
use function mb_list_encodings;
use function preg_match;
use function sprintf;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_bucket_new;
use function stream_filter_register;
use function stream_get_filters;
use function strtolower;
use function substr;

use const PSFS_ERR_FATAL;
use const PSFS_FEED_ME;
use const PSFS_PASS_ON;
use const STREAM_FILTER_READ;
use const STREAM_FILTER_WRITE;

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
    protected string $buffer = '';

    /**
     * Static method to register the class as a stream filter.
     */
    public static function register(): void
    {
        $filter_name = self::FILTERNAME.'.*';

        in_array($filter_name, stream_get_filters(), true) || stream_filter_register($filter_name, self::class);
    }

    /**
     * Static method to add the stream filter to a {@link AbstractCsv} object.
     */
    public static function addTo(AbstractCsv $csv, string $input_encoding, string $output_encoding, ?array $params = null): AbstractCsv
    {
        self::register();

        if ($csv instanceof Reader) {
            return $csv->appendStreamFilterOnRead(self::getFiltername($input_encoding, $output_encoding), $params);
        }

        return $csv->appendStreamFilterOnWrite(self::getFiltername($input_encoding, $output_encoding), $params);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function appendOnReadTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::appendFilter($stream, STREAM_FILTER_READ, $input_encoding, $output_encoding);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function appendOnWriteTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::appendFilter($stream, STREAM_FILTER_WRITE, $input_encoding, $output_encoding);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function prependOnReadTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::prependFilter($stream, STREAM_FILTER_READ, $input_encoding, $output_encoding);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function prependOnWriteTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::prependFilter($stream, STREAM_FILTER_WRITE, $input_encoding, $output_encoding);
    }

    /**
     * @param resource $stream
     *
     * @throws RuntimeException|TypeError
     *
     * @return resource
     */
    final protected static function appendFilter(mixed $stream, int $mode, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        self::register();
        $filtername = self::getFiltername($input_encoding, $output_encoding);

        /** @var resource|false $filter */
        $filter = Warning::cloak(stream_filter_append(...), self::filterStream($stream), $filtername, $mode);
        is_resource($filter) || throw new RuntimeException('Could not append the registered stream filter: '.$filtername);

        return $filter;
    }

    /**
     * @param resource $stream
     *
     * @throws RuntimeException|TypeError
     *
     * @return resource
     */
    final protected static function prependFilter(mixed $stream, int $mode, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        self::register();
        $filtername = self::getFiltername($input_encoding, $output_encoding);

        /** @var resource|false $filter */
        $filter = Warning::cloak(stream_filter_prepend(...), self::filterStream($stream), $filtername, $mode);
        is_resource($filter) || throw new RuntimeException('Could not append the registered stream filter: '.$filtername);

        return $filter;
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     *
     * @return resource
     */
    final protected static function filterStream(mixed $stream): mixed
    {
        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        'stream' === ($type = get_resource_type($stream)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        return $stream;
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
    final protected static function filterEncoding(string $encoding): string
    {
        static $encoding_list;

        $encoding_list ??= array_reduce(mb_list_encodings(), fn (array $list, string $encoding): array => [...$list, ...[strtolower($encoding) => $encoding]], []);

        return $encoding_list[strtolower($encoding)] ?? throw new OutOfRangeException('The submitted charset '.$encoding.' is not supported by the mbstring extension.');
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
        $inputBuffer = $this->buffer;
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $inputBuffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        if ('' === $inputBuffer && !$closing) {
            return PSFS_FEED_ME;
        }

        if ($this->skipBomSequence && null !== ($bom = Bom::tryFromSequence($inputBuffer))) {
            $inputBuffer = substr($inputBuffer, $bom->length());
        }

        // if the stream content is invalid then we store it and
        // ask for more content to try to correctly convert the data
        if (!mb_check_encoding($inputBuffer, $this->input_encoding) && !$closing) {
            $this->buffer = $inputBuffer;

            return PSFS_FEED_ME;
        }

        try {
            Warning::cloak(function () use ($inputBuffer, $out) {
                $outputBuffer = (string) mb_convert_encoding($inputBuffer, $this->output_encoding, $this->input_encoding);
                $streamBucket = stream_bucket_new($this->stream, $outputBuffer);

                stream_bucket_append($out, $streamBucket);
            });
            return PSFS_PASS_ON;
        } catch (Throwable) {
            return PSFS_ERR_FATAL;
        } finally {
            $this->buffer = '';
        }
    }

    /**
     * Converts Csv records collection into UTF-8.
     */
    public function convert(iterable $records): iterable
    {
        return match (true) {
            $this->output_encoding === $this->input_encoding => $records,
            is_array($records) => array_map($this, $records),
            default => MapIterator::fromIterable($records, $this),
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
    final protected function encodeField(int|float|string|null $value, int|string $offset): array
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

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @codeCoverageIgnore
     * @see self::appendOnReadTo()
     * @see self::appendOnWriteTo()
     * @deprecated since version 9.22.0
     *
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    #[Deprecated(message:'use League\Csv\CharserConverter::appendOnReadTo() or League\Csv\CharserConverter::appendOnWriteTo() instead', since:'league/csv:9.22.0')]
    public static function appendTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::appendFilter($stream, 0, $input_encoding, $output_encoding);
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @codeCoverageIgnore
     * @see self::prependOnReadTo()
     * @see self::prependOnWriteTo()
     * @deprecated since version 9.22.0
     *
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    #[Deprecated(message:'use League\Csv\CharserConverter::prependOnReadTo() or League\Csv\CharserConverter::prependOnWriteTo() instead', since:'league/csv:9.22.0')]
    public static function prependTo(mixed $stream, string $input_encoding = 'UTF-8', string $output_encoding = 'UTF-8'): mixed
    {
        return self::prependFilter($stream, 0, $input_encoding, $output_encoding);
    }

    /**
     * Static method to add the stream filter to a {@link Reader} object to handle BOM skipping.
     */
    public static function addBOMSkippingTo(Reader $document, string $output_encoding = 'UTF-8'): Reader
    {
        self::register();

        $document->appendStreamFilterOnRead(
            self::getFiltername((Bom::tryFrom($document->getInputBOM()) ?? Bom::Utf8)->encoding(), $output_encoding),
            [self::BOM_SEQUENCE => self::SKIP_BOM_SEQUENCE]
        );

        return $document;
    }
}
