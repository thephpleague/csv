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
use LogicException;
use OutOfBoundsException;
use php_user_filter;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use Throwable;
use ValueError;

use function array_keys;
use function in_array;
use function restore_error_handler;
use function set_error_handler;
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_bucket_new;
use function stream_filter_register;
use function stream_get_filters;

use const PSFS_ERR_FATAL;
use const PSFS_FEED_ME;
use const PSFS_PASS_ON;

final class CallbackStreamFilter extends php_user_filter
{
    /** @var array<string, Closure(string, mixed): string> */
    private static array $filters = [];

    /** @var ?Closure(string, mixed): string */
    private ?Closure $callback;

    public function onCreate(): bool
    {
        $this->callback = self::$filters[$this->filtername] ?? null;

        return $this->callback instanceof Closure;
    }

    public function onClose(): void
    {
        $this->callback = null;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $data = '';
        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $data .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        if (null === $this->callback) {
            return PSFS_FEED_ME;
        }

        try {
            $data = ($this->callback)($data, $this->params);
        } catch (Throwable $exception) {
            $this->onClose();
            trigger_error('An error occurred while executing the stream filter `'.$this->filtername.'`: '.$exception->getMessage(), E_USER_WARNING);

            return PSFS_ERR_FATAL;
        }

        set_error_handler(fn (int $errno, string $errstr, string $errfile, int $errline) => true);
        stream_bucket_append($out, stream_bucket_new($this->stream, $data));
        restore_error_handler();

        return PSFS_PASS_ON;
    }

    /**
     * Static method to register the class as a stream filter.
     *
     * @param callable(string, mixed): string $callback
     */
    public static function register(string $filtername, callable $callback): void
    {
        if (isset(self::$filters[$filtername]) || in_array($filtername, stream_get_filters(), true)) {
            throw new LogicException('The stream filter "'.$filtername.'" is already registered.');
        }

        $callback = self::normalizeCallback($callback);
        if (!stream_filter_register($filtername, self::class)) {
            throw new RuntimeException('The stream filter "'.$filtername.'" could not be registered.');
        }

        self::$filters[$filtername] = $callback;
    }

    /**
     * @param callable(string, mixed): string $callback
     *
     * @throws ReflectionException|ValueError
     *
     * @return Closure(string, mixed): string
     */
    private static function normalizeCallback(callable $callback): Closure
    {
        if (!$callback instanceof Closure) {
            $callback = $callback(...);
        }

        $reflection = new ReflectionFunction($callback);
        if (!$reflection->isInternal()) {
            return $callback;
        }

        if (1 !== $reflection->getNumberOfParameters()) {
            throw new ValueError('The PHP function "'.$reflection->getName().'" can not be used directly; wrap it in a callback.');
        }

        return fn (string $bucket, mixed $params): string => $callback($bucket);
    }

    /**
     * Tells whether a callback with the given name is already registered or not.
     */
    public static function isRegistered(string $filtername): bool
    {
        return isset(self::$filters[$filtername]);
    }

    /**
     * Returns the list of registered filters.
     *
     * @return array<string>
     */
    public static function registeredFilternames(): array
    {
        return array_keys(self::$filters);
    }

    /**
     * Returns the closure attached to the filtername.
     *
     * @throws OutOfBoundsException if no callback is attached to the filter name
     *
     * @return Closure(string, mixed): string
     */
    public static function callback(string $filtername): Closure
    {
        return self::$filters[$filtername] ?? throw new OutOfBoundsException('No callback is attached to the stream filter "'.$filtername.'".');
    }
}
