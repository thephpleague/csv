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

use LogicException;
use RuntimeException;
use TypeError;

use function get_resource_type;
use function gettype;
use function in_array;
use function is_resource;
use function stream_get_filters;

use const STREAM_FILTER_READ;
use const STREAM_FILTER_WRITE;

final class StreamFilter
{
    /**
     * Remove a filter from a stream.
     *
     * @param resource $stream_filter
     */
    public static function remove($stream_filter): bool
    {
        if (!is_resource($stream_filter)) {
            throw new TypeError('Argument passed must be a stream resource, '.gettype($stream_filter).' given.');
        }

        if ('stream filter' !== ($type = get_resource_type($stream_filter))) {
            throw new TypeError('Argument passed must be a stream filter resource, '.$type.' resource given');
        }

        return stream_filter_remove($stream_filter);
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    public static function appendOnReadTo(mixed $stream, string $filtername, mixed $params = null): mixed
    {
        return self::appendFilter(STREAM_FILTER_READ, $stream, $filtername, $params);
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    public static function appendOnWriteTo(mixed $stream, string $filtername, mixed $params = null): mixed
    {
        return self::appendFilter(STREAM_FILTER_WRITE, $stream, $filtername, $params);
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    public static function prependOnReadTo(mixed $stream, string $filtername, mixed $params = null): mixed
    {
        return self::prependFilter(STREAM_FILTER_READ, $stream, $filtername, $params);
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    public static function prependOnWriteTo(mixed $stream, string $filtername, mixed $params = null): mixed
    {
        return self::prependFilter(STREAM_FILTER_WRITE, $stream, $filtername, $params);
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    private static function prependFilter(int $mode, mixed $stream, string $filtername, mixed $params): mixed
    {
        self::filterFiltername($filtername);
        self::filterStream($stream);

        if ($stream instanceof AbstractCsv) {
            return match ($mode) {
                STREAM_FILTER_WRITE => $stream->prependStreamFilterOnWrite($filtername, $params),
                default => $stream->prependStreamFilterOnRead($filtername, $params),
            };
        }

        /** @var resource|false $filter */
        $filter = Warning::cloak(stream_filter_prepend(...), $stream, $filtername, $mode, $params);
        if (!is_resource($filter)) {
            throw new RuntimeException('Could not append the registered stream filter: '.$filtername);
        }

        return $filter;
    }

    /**
     * @param resource|AbstractCsv $stream
     *
     * @throws TypeError
     * @throws RuntimeException
     *
     * @return resource|AbstractCsv
     */
    private static function appendFilter(int $mode, mixed $stream, string $filtername, mixed $params): mixed
    {
        self::filterFiltername($filtername);
        self::filterStream($stream);

        if ($stream instanceof AbstractCsv) {
            return match ($mode) {
                STREAM_FILTER_WRITE => $stream->appendStreamFilterOnWrite($filtername, $params),
                default => $stream->appendStreamFilterOnRead($filtername, $params),
            };
        }

        /** @var resource|false $filter */
        $filter = Warning::cloak(stream_filter_append(...), $stream, $filtername, $mode, $params);
        if (!is_resource($filter)) {
            throw new RuntimeException('Could not append the registered stream filter: '.$filtername);
        }

        return $filter;
    }

    private static function filterFiltername(string $filtername): void
    {
        if (!in_array($filtername, stream_get_filters(), true)) {
            throw new LogicException('The stream filter "'.$filtername.'" is not registered.');
        }
    }

    /**
     * Validate the resource given.
     *
     * @throws TypeError if the resource given is not a stream
     */
    private static function filterStream(mixed $stream): void
    {
        if ($stream instanceof AbstractCsv) {
            return;
        }

        if (!is_resource($stream)) {
            throw new TypeError('Argument passed must be a stream resource, '.gettype($stream).' given.');
        }

        if ('stream' !== ($type = get_resource_type($stream))) {
            throw new TypeError('Argument passed must be a stream resource, '.$type.' resource given');
        }
    }
}
