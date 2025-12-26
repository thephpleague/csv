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
use Closure;
use JsonSerializable;
use TypeError;
use UnitEnum;

/**
 * Formatting strategies (exclusive):
 * - Name (UnitEnum::name)
 * - Value (BackedEnum::value)
 * - JSON (JsonSerializable)
 * - callback
 */
final class EnumFormatter
{
    private const NAME_FORMAT = 'name';
    private const VALUE_FORMAT = 'value';
    private const JSON_FORMAT = 'json';
    private const CALLBACK_FORMAT = 'callback';

    private function __construct(
        private readonly string $format,
        private readonly ?Closure $callback
    ){
    }

    public static function usingName(): self
    {
        return new self(self::NAME_FORMAT, null);
    }

    public static function usingValue(): self
    {
        return new self(self::VALUE_FORMAT, null);
    }

    public static function usingJson(): self
    {
        return new self(self::JSON_FORMAT, null);
    }

    /**
     * Register a callback that will convert the UnitEnum in a representation suitable
     * to be used by PHP's fputcsv and fwrite functions without emitting errors
     *
     * @param callable(UnitEnum): mixed $callback
     */
    public static function usingCallback(callable $callback): self
    {
        return new self(self::CALLBACK_FORMAT, $callback instanceof Closure ? $callback : $callback(...));
    }

    /**
     * Enable using the class as a formatter for the {@link Writer}.
     *
     * @throws TypeError if encoding is invalid
     */
    public function __invoke(array $record): array
    {
        return array_map(fn (mixed $value) => !$value instanceof UnitEnum ? $value : $this->encode($value), $record);
    }

    /**
     * Encodes the UnitEnum in a representation suitable to be used
     * by PHP's fputcsv and fwrite functions without emitting errors
     *
     * @throws TypeError If the encoding does not work
     */
    public function encode(UnitEnum $value): mixed
    {
        return match (true) {
            self::NAME_FORMAT === $this->format => $value->name,
            self::VALUE_FORMAT === $this->format && $value instanceof BackedEnum => $value->value,
            self::JSON_FORMAT === $this->format && $value instanceof JsonSerializable => $value->jsonSerialize(),
            self::CALLBACK_FORMAT === $this->format && null !== $this->callback => ($this->callback)($value),
            default => throw new TypeError('The Enum `'.$value::class.'` cannot be encoded using the "'.$this->format.'" strategy.'),
        };
    }
}
