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

use BackedEnum;
use TypeError;
use ValueError;

use function get_debug_type;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_nan;
use function is_scalar;
use function json_encode;

final readonly class TypeFormatter
{
    public function __construct(
        public string $trueValue = '1',
        public string $falseValue = '0',
        public string $nullValue = '',
    ) {
    }

    /**
     * Enable using the class as a formatter for the {@link Writer}.
     *
     * @throws TypeError if type encoding is not supported
     * @throws ValueError if encoding is not possible for valid type
     *
     * @return array<array-key, string>
     */
    public function __invoke(array $record): array
    {
        $encoded = [];
        foreach ($record as $key => $value) {
            $encoded[$key] = $this->encode($value, $key);
        }

        return $encoded;
    }

    /**
     * Convert a single value.
     *
     * @throws TypeError if type encoding is not supported
     * @throws ValueError if encoding is not possible for valid type
     */
    public function encode(mixed $value, string|int|null $name = null): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            null === $value => $this->nullValue,
            is_bool($value) => $value ? $this->trueValue : $this->falseValue,
            is_float($value) => self::floatToString($value, $name),
            is_scalar($value) => (string) $value,
            default => throw new TypeError(
                null === $name
                    ? 'Value must be of type null|bool|int|float|string|BackedEnum; '.get_debug_type($value).' given.'
                    : 'Field “'.$name.'” value must be of type null|bool|int|float|string|BackedEnum; '.get_debug_type($value).' given.'
            ),
        };
    }

    /**
     * @throws ValueError if encoding is not possible
     */
    private static function floatToString(float $value, string|int|null $name = null): string
    {
        return match (true) {
            is_nan($value) => 'NAN',
            is_infinite($value) => $value > 0 ? 'INF' : '-INF',
            false !== ($encoded = json_encode($value)) => $encoded,
            default => throw new ValueError(
                null === $name
                    ? 'Unable to encode the float value.'
                    : 'Unable to encode the float value from Field “'.$name.'”'
            ),
        };
    }
}
