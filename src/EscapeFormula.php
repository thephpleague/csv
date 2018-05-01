<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.1.4
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use InvalidArgumentException;

/**
 * A League CSV formatter to tackle CSV Formula Injection
 *
 * @see http://georgemauer.net/2017/10/07/csv-injection.html
 *
 * @package League.csv
 * @since   9.1.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class EscapeFormula
{
    /**
     * Spreadsheet formula starting character
     */
    const FORMULA_STARTING_CHARS = ['=', '-', '+', '@'];

    /**
     * Effective Spreadsheet formula starting characters
     *
     * @var array
     */
    protected $special_chars = [];

    /**
     * Escape character to escape each CSV formula field
     *
     * @var string
     */
    protected $escape;

    /**
     * New instance
     *
     * @param string   $escape        escape character to escape each CSV formula field
     * @param string[] $special_chars additional spreadsheet formula starting characters
     *
     */
    public function __construct(string $escape = "\t", array $special_chars = [])
    {
        $this->escape = $escape;
        if (!empty($special_chars)) {
            $special_chars = $this->filterSpecialCharacters(...$special_chars);
        }

        $chars = array_merge(self::FORMULA_STARTING_CHARS, $special_chars);
        $chars = array_unique($chars);
        $this->special_chars = array_fill_keys($chars, 1);
    }

    /**
     * Filter submitted special characters.
     *
     * @param string ...$characters
     *
     * @throws InvalidArgumentException if the string is not a single character
     *
     * @return string[]
     */
    protected function filterSpecialCharacters(string ...$characters): array
    {
        foreach ($characters as $str) {
            if (1 != strlen($str)) {
                throw new InvalidArgumentException(sprintf('The submitted string %s must be a single character', $str));
            }
        }

        return $characters;
    }

    /**
     * Returns the list of character the instance will escape.
     *
     * @return string[]
     */
    public function getSpecialCharacters(): array
    {
        return array_keys($this->special_chars);
    }

    /**
     * Returns the escape character.
     *
     * @return string
     */
    public function getEscape(): string
    {
        return $this->escape;
    }

    /**
     * League CSV formatter hook.
     *
     * @see escapeRecord
     *
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record): array
    {
        return $this->escapeRecord($record);
    }

    /**
     * Escape a CSV record.
     *
     * @param array $record
     *
     * @return array
     */
    public function escapeRecord(array $record): array
    {
        return array_map([$this, 'escapeField'], $record);
    }

    /**
     * Escape a CSV cell.
     *
     * @param mixed $cell
     *
     * @return mixed
     */
    protected function escapeField($cell)
    {
        if (!$this->isStringable($cell)) {
            return $cell;
        }

        $str_cell = (string) $cell;
        if (isset($str_cell[0], $this->special_chars[$str_cell[0]])) {
            return $this->escape.$str_cell;
        }

        return $cell;
    }

    /**
     * Tell whether the submitted value is stringable.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isStringable($value): bool
    {
        return is_string($value) || (is_object($value) && method_exists($value, '__toString'));
    }
}
