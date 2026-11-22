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
use function stream_bucket_append;
use function stream_bucket_make_writeable;
use function stream_bucket_new;
use function stream_filter_register;
use function stream_get_filters;
use function strlen;
use function trigger_error;

use const E_USER_WARNING;
use const PSFS_ERR_FATAL;
use const PSFS_FEED_ME;
use const PSFS_PASS_ON;

final class CallbackStreamFilter extends php_user_filter
{
    /** @var array<string, Closure(string, mixed, bool): ?string> */
    private static array $filters = [];
    private string $buffer = '';
    /**
     * The limit on the accumulated input, not necessarily the size of an individual bucket.
     * @var positive-int
     */
    private const MAX_BUFFER_LENGTH = 65_536;
    /** @var ?Closure(string, mixed, bool): ?string */
    private ?Closure $callback = null;

    public function onCreate(): bool
    {
        $this->callback = self::$filters[$this->filtername] ?? null;
        $this->buffer = '';

        return $this->callback instanceof Closure;
    }

    public function onClose(): void
    {
        $this->buffer = '';
        $this->callback = null;
    }

    public function filter($in, $out, &$consumed, bool $closing): int
    {
        if (null === $this->callback) {
            return PSFS_FEED_ME;
        }

        $inputBuffer = $this->buffer;

        while (null !== ($bucket = stream_bucket_make_writeable($in))) {
            $inputBuffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        try {
            $result = ($this->callback)($inputBuffer, $this->params, $closing);
            if (null === $result) {
                if (!$closing) {
                    if (strlen($inputBuffer) <= self::MAX_BUFFER_LENGTH) {
                        $this->buffer = $inputBuffer;

                        return PSFS_FEED_ME;
                    }

                    $this->onClose();
                    trigger_error('The stream filter `'.$this->filtername.'` exceeded the maximum buffer length.', E_USER_WARNING);

                    return PSFS_ERR_FATAL;
                }

                $result = $inputBuffer;
            }

            $this->buffer = '';

            if ('' !== $result) {
                stream_bucket_append($out, stream_bucket_new($this->stream, $result));
            }

            return PSFS_PASS_ON;
        } catch (Throwable $exception) {
            $this->onClose();
            trigger_error('An error occurred while executing the stream filter `'.$this->filtername.'`: '.$exception->getMessage(), E_USER_WARNING);

            return PSFS_ERR_FATAL;
        }
    }

    /**
     * Static method to register the class as a stream filter.
     *
     * @param callable(string, mixed, bool): ?string $callback
     *
     * @throws ReflectionException
     */
    public static function register(string $filtername, callable $callback): void
    {
        if (isset(self::$filters[$filtername]) || in_array($filtername, stream_get_filters(), true)) {
            throw new LogicException('The stream filter "'.$filtername.'" is already registered.');
        }

        $callback = self::normalizeCallback($callback);
        stream_filter_register($filtername, self::class) || throw new RuntimeException('The stream filter "'.$filtername.'" could not be registered.');

        self::$filters[$filtername] = $callback;
    }

    /**
     * @throws ReflectionException|ValueError
     *
     * @return Closure(string, mixed, bool): ?string
     */
    private static function normalizeCallback(callable $callback): Closure
    {
        if (!$callback instanceof Closure) {
            $callback = $callback(...);
        }

        $reflection = new ReflectionFunction($callback);
        $numberOfParameters = $reflection->getNumberOfParameters();

        !$reflection->isInternal() || 1 === $numberOfParameters || throw new ValueError('The PHP function "'.$reflection->getName().'" can not be used directly; wrap it in a callback.');

        return match (true) {
            1 === $numberOfParameters => fn (string $bucket, mixed $params, bool $closing): ?string => $callback($bucket),
            2 === $numberOfParameters => fn (string $bucket, mixed $params, bool $closing): ?string => $callback($bucket, $params),
            3 === $numberOfParameters => $callback,
            default => throw new ValueError('The callback must accept between one and three parameters.'),
        };
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
     * @return Closure(string, mixed, bool): ?string
     */
    public static function callback(string $filtername): Closure
    {
        return self::$filters[$filtername] ?? throw new OutOfBoundsException('No callback is attached to the stream filter "'.$filtername.'".');
    }
}
