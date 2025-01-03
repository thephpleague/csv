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
use function str_replace;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_filter_register;
use function stream_get_filters;

use const PSFS_PASS_ON;

final class SwapDelimiter extends php_user_filter
{
    private const FILTER_NAME = 'string.league.csv.delimiter';
    public const MODE_READ = 'read';
    public const MODE_WRITE = 'write';
    private string $search = '';
    private string $replace = '';

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

        if ($csv instanceof Reader) {
            $csv->appendStreamFilterOnRead(self::getFiltername(), [
                'mb_separator' => $inputDelimiter,
                'separator' => $csv->getDelimiter(),
                'mode' => self::MODE_READ,
            ]);
            return;
        }

        $csv->appendStreamFilterOnWrite(self::getFiltername(), [
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

        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        'stream' === ($type = get_resource_type($stream)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $filter = stream_filter_append($stream, self::getFiltername(), params: [
            'mb_separator' => $inputDelimiter,
            'separator' => $delimiter,
            'mode' => str_contains(stream_get_meta_data($stream)['mode'], 'r') ? self::MODE_READ : self::MODE_WRITE,
        ]);
        restore_error_handler();

        is_resource($filter) || throw new RuntimeException('Could not append the registered stream filter: '.self::getFiltername());

        return $filter;
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

        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        'stream' === ($type = get_resource_type($stream)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        $filtername = self::getFiltername();
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $filter = stream_filter_append($stream, $filtername, params: [
            'mb_separator' => $inputDelimiter,
            'separator' => $delimiter,
            'mode' => str_contains(stream_get_meta_data($stream)['mode'], 'r') ? self::MODE_READ : self::MODE_WRITE,
        ]);
        restore_error_handler();

        is_resource($filter) || throw new RuntimeException('Could not prepend the registered stream filter: '.$filtername);

        return $filter;
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
        [$this->search, $this->replace] = match ($mode) {
            self::MODE_READ => [trim($this->params['mb_separator'] ?? ''), trim($this->params['separator'] ?? '')],
            self::MODE_WRITE => [trim($this->params['separator'] ?? ''), trim($this->params['mb_separator'] ?? '')],
            default => ['', ''],
        };

        return !in_array('', [$this->replace, $this->search], true);
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $data = '';
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $data .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        $data = str_replace($this->search, $this->replace, $data);
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        stream_bucket_append($out, stream_bucket_new($this->stream, $data));
        restore_error_handler();

        return PSFS_PASS_ON;
    }
}
