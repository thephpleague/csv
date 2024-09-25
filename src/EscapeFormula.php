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

use InvalidArgumentException;
use Stringable;

use function array_fill_keys;
use function array_keys;
use function array_map;
use function is_string;

/**
 * A Formatter to tackle CSV Formula Injection.
 *
 * @see http://georgemauer.net/2017/10/07/csv-injection.html
 */
class EscapeFormula
{
    /** Spreadsheet formula starting character. */
    public const FORMULA_STARTING_CHARS = ['=', '-', '+', '@', "\t", "\r"];

    /** Effective Spreadsheet formula starting characters. */
    protected array $special_chars = [];

    /**
     * @param string $escape escape character to escape each CSV formula field
     * @param array<string> $special_chars additional spreadsheet formula starting characters
     */
    public function __construct(
        protected string $escape = "'",
        array $special_chars = []
    ) {
        $this->special_chars = array_fill_keys([
            ...self::FORMULA_STARTING_CHARS,
            ...$this->filterSpecialCharacters(...$special_chars),
        ], 1);
    }

    /**
     * Filter submitted special characters.
     *
     * @throws InvalidArgumentException if the string is not a single character
     *
     * @return array<string>
     */
    protected function filterSpecialCharacters(string ...$characters): array
    {
        foreach ($characters as $str) {
            1 === strlen($str) || throw new InvalidArgumentException('The submitted string '.$str.' must be a single character');
        }

        return $characters;
    }

    /**
     * Returns the list of character the instance will escape.
     *
     * @return array<string>
     */
    public function getSpecialCharacters(): array
    {
        return array_keys($this->special_chars);
    }

    /**
     * Returns the escape character.
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * Escapes a CSV record.
     */
    public function escapeRecord(array $record): array
    {
        return array_map($this->escapeField(...), $record);
    }

    public function unescapeRecord(array $record): array
    {
        return array_map($this->unescapeField(...), $record);
    }

    /**
     * Escapes a CSV cell if its content is stringable.
     */
    protected function escapeField(mixed $cell): mixed
    {
        $strOrNull = match (true) {
            is_string($cell) => $cell,
            $cell instanceof Stringable => (string) $cell,
            default => null,
        };

        return match (true) {
            null == $strOrNull,
            !isset($strOrNull[0], $this->special_chars[$strOrNull[0]]) => $cell,
            default => $this->escape.$strOrNull,
        };
    }

    protected function unescapeField(mixed $cell): mixed
    {
        $strOrNull = match (true) {
            is_string($cell) => $cell,
            $cell instanceof Stringable => (string) $cell,
            default => null,
        };

        return match (true) {
            null === $strOrNull,
            !isset($strOrNull[0], $strOrNull[1]),
            $strOrNull[0] !== $this->escape,
            !isset($this->special_chars[$strOrNull[1]]) => $cell,
            default => substr($strOrNull, 1),
        };
    }

    /**
     * @deprecated since 9.7.2 will be removed in the next major release
     * @codeCoverageIgnore
     *
     * Tells whether the submitted value is stringable.
     *
     * @param mixed $value value to check if it is stringable
     */
    protected function isStringable(mixed $value): bool
    {
        return is_string($value) || $value instanceof Stringable;
    }

    /**
     * @deprecated since 9.11.0 will be removed in the next major release
     * @codeCoverageIgnore
     *
     * League CSV formatter hook.
     *
     * @see escapeRecord
     */
    public function __invoke(array $record): array
    {
        return $this->escapeRecord($record);
    }
}
