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
    private string $search;
    private string $replace;

    public static function getFiltername(): string
    {
        return self::FILTER_NAME;
    }

    /**
     * Static method to register the class as a stream filter.
     */
    public static function register(): void
    {
        if (!in_array(self::FILTER_NAME, stream_get_filters(), true)) {
            stream_filter_register(self::FILTER_NAME, self::class);
        }
    }

    /**
     * Static method to attach the stream filter to a CSV Reader or Writer instance.
     */
    public static function addTo(AbstractCsv $csv, string $inputDelimiter): void
    {
        self::register();

        $csv->addStreamFilter(self::getFiltername(), [
            'mb_separator' => $inputDelimiter,
            'separator' => $csv->getDelimiter(),
            'mode' => $csv instanceof Writer ? self::MODE_WRITE : self::MODE_READ,
        ]);
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
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $content = $bucket->data;
            $bucket->data = str_replace($this->search, $this->replace, $content);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
