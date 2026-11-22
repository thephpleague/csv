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

use php_user_filter;
use RuntimeException;
use TypeError;

use function in_array;
use function is_array;
use function is_string;
use function min;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_bucket_new;
use function stream_filter_register;
use function stream_get_filters;
use function stream_get_meta_data;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const PSFS_FEED_ME;
use const PSFS_PASS_ON;

final class SwapDelimiter extends php_user_filter
{
    private const FILTER_NAME = 'string.league.csv.delimiter';
    public const MODE_READ = 'read';
    public const MODE_WRITE = 'write';
    private string $search = '';
    private string $replace = '';
    private string $buffer = '';

    public static function getFiltername(): string
    {
        return self::FILTER_NAME;
    }

    /**
     * Static method to register the class as a stream filter.
     */
    public static function register(): void
    {
        in_array(self::FILTER_NAME, stream_get_filters(), true) || stream_filter_register(self::FILTER_NAME, self::class);
    }

    /**
     * Static method to attach the stream filter to a CSV Reader or Writer instance.
     */
    public static function addTo(AbstractCsv $csv, string $inputDelimiter): void
    {
        self::register();

        $csv instanceof Reader
            ? StreamFilter::appendOnReadTo($csv, self::getFiltername(), [
                'mb_separator' => $inputDelimiter,
                'separator' => $csv->getDelimiter(),
                'mode' => self::MODE_READ,
            ])
            : StreamFilter::appendOnWriteTo($csv, self::getFiltername(), [
                'mb_separator' => $inputDelimiter,
                'separator' => $csv->getDelimiter(),
                'mode' => self::MODE_WRITE,
            ]);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function appendTo(mixed $stream, string $inputDelimiter, string $delimiter): mixed
    {
        self::register();
        $params = [
            'mb_separator' => $inputDelimiter,
            'separator' => $delimiter,
            'mode' => str_contains(stream_get_meta_data($stream)['mode'], 'r') ? self::MODE_READ : self::MODE_WRITE,
        ];

        return self::MODE_READ === $params['mode']
            ? StreamFilter::appendOnReadTo($stream, self::getFiltername(), $params)
            : StreamFilter::appendOnWriteTo($stream, self::getFiltername(), $params);
    }

    /**
     * @param resource $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function prependTo(mixed $stream, string $inputDelimiter, string $delimiter): mixed
    {
        self::register();
        $params = [
            'mb_separator' => $inputDelimiter,
            'separator' => $delimiter,
            'mode' => str_contains(stream_get_meta_data($stream)['mode'], 'r') ? self::MODE_READ : self::MODE_WRITE,
        ];

        return self::MODE_READ === $params['mode']
            ? StreamFilter::prependOnReadTo($stream, self::getFiltername(), $params)
            : StreamFilter::prependOnWriteTo($stream, self::getFiltername(), $params);
    }

    public function onCreate(): bool
    {
        if (self::FILTER_NAME !== $this->filtername) {
            return false;
        }

        if (!is_array($this->params)) {
            return false;
        }

        $mode = $this->params['mode'] ?? '';
        $mb_separator = $this->params['mb_separator'] ?? '';
        $separator = $this->params['separator'] ?? '';
        if (!is_string($mb_separator) || !is_string($separator)) {
            return false;
        }

        [$this->search, $this->replace] = match ($mode) {
            self::MODE_READ => [trim($mb_separator), trim($separator)],
            self::MODE_WRITE => [trim($separator), trim($mb_separator)],
            default => ['', ''],
        };

        if (in_array('', [$this->replace, $this->search], true)) {
            return false;
        }

        $this->buffer = '';
        return true;
    }

    public function onClose(): void
    {
        $this->buffer = '';
        parent::onClose();
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $data = $this->buffer;
        $this->buffer = '';

        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $data .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        $output = '';
        $searchLength = strlen($this->search);
        $offset = 0;

        while (false !== ($position = strpos($data, $this->search, $offset))) {
            $output .= substr($data, $offset, $position - $offset).$this->replace;
            $offset = $position + $searchLength;
        }

        $remaining = substr($data, $offset);

        if (!$closing) {
            for ($length = min($searchLength - 1, strlen($remaining)); 0 < $length; --$length) {
                if (substr($remaining, -$length) === substr($this->search, 0, $length)) {
                    $this->buffer = substr($remaining, -$length);
                    $remaining = substr($remaining, 0, -$length);

                    break;
                }
            }
        }

        $output .= $remaining;
        if ('' === $output) {
            return PSFS_FEED_ME;
        }

        Warning::cloak(function () use ($output, $out) {
            stream_bucket_append($out, stream_bucket_new($this->stream, $output));
        });

        return PSFS_PASS_ON;
    }
}
