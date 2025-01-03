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

use Closure;
use php_user_filter;
use RuntimeException;
use TypeError;

use function array_key_exists;
use function is_resource;

final class CallbackStreamFilter extends php_user_filter
{
    private const FILTER_NAME = 'string.league.csv.stream.callback.filter';

    public static function getFiltername(string $name): string
    {
        return self::FILTER_NAME.'.'.$name;
    }

    /**
     * Static method to register the class as a stream filter.
     */
    public static function register(string $name): void
    {
        $filtername = self::getFiltername($name);
        if (!in_array($filtername, stream_get_filters(), true)) {
            stream_filter_register($filtername, self::class);
        }
    }

    /**
     * Static method to attach the stream filter to a CSV Reader or Writer instance.
     */
    public static function addTo(AbstractCsv $csv, string $name, callable $callback): void
    {
        self::register($name);

        $csv->addStreamFilter(self::getFiltername($name), [
            'name' => $name,
            'callback' => $callback instanceof Closure ? $callback : $callback(...),
        ]);
    }

    /**
     * @param resource $stream
     * @param callable(string): string $callback
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function appendTo(mixed $stream, string $name, callable $callback): mixed
    {
        self::register($name);

        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        'stream' === ($type = get_resource_type($stream)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $filter = stream_filter_append($stream, self::getFiltername($name), params: [
            'name' => $name,
            'callback' => $callback instanceof Closure ? $callback : $callback(...),
        ]);
        restore_error_handler();

        if (!is_resource($filter)) {
            throw new RuntimeException('Could not append the registered stream filter: '.self::getFiltername($name));
        }

        return $filter;
    }

    /**
     * @param resource $stream
     * @param callable(string): string $callback
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource
     */
    public static function prependTo(mixed $stream, string $name, callable $callback): mixed
    {
        self::register($name);

        is_resource($stream) || throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        'stream' === ($type = get_resource_type($stream)) || throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');

        $filtername = self::getFiltername($name);
        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        $filter = stream_filter_append($stream, $filtername, params: [
            'name' => $name,
            'callback' => $callback instanceof Closure ? $callback : $callback(...),
        ]);
        restore_error_handler();

        if (!is_resource($filter)) {
            throw new RuntimeException('Could not append the registered stream filter: '.self::getFiltername($name));
        }

        return $filter;
    }

    public function onCreate(): bool
    {
        return is_array($this->params) &&
            array_key_exists('name', $this->params) &&
            self::getFiltername($this->params['name']) === $this->filtername &&
            array_key_exists('callback', $this->params) &&
            $this->params['callback'] instanceof Closure
        ;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        /** @var Closure(string): string $callback */
        $callback = $this->params['callback']; /* @phpstan-ignore-line */
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $bucket->data = ($callback)($bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
