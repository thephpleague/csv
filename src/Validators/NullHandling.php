<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Validators;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class NullHandling
{
    /**
     * set null handling mode to throw exception
     */
    const NULL_AS_EXCEPTION = 1;

    /**
     * set null handling mode to remove cell
     */
    const NULL_AS_SKIP_CELL = 2;

    /**
     * set null handling mode to convert null into empty string
     */
    const NULL_AS_EMPTY = 3;

    /**
     * disable null handling
     */
    const NULL_HANDLING_DISABLED = 4;

    /**
     * the object current null handling mode
     *
     * @var int
     */
    private $null_handling_mode = self::NULL_AS_EXCEPTION;

    /**
     * Tell the class how to handle null value
     *
     * @param int $value a Writer null behavior constant
     *
     * @throws \OutOfBoundsException If the Integer is not valid
     *
     * @return static
     */
    public function setNullHandlingMode($value)
    {
        if (! in_array($value, [
                self::NULL_AS_SKIP_CELL,
                self::NULL_AS_EXCEPTION,
                self::NULL_AS_EMPTY,
                self::NULL_HANDLING_DISABLED,
            ])) {
            throw new OutOfBoundsException('invalid value for null handling');
        }
        $this->null_handling_mode = $value;

        return $this;
    }

    /**
     * null handling getter
     *
     * @return int
     */
    public function getNullHandlingMode()
    {
        return $this->null_handling_mode;
    }


    /**
     * Is the submitted row valid
     *
     * @param array $row
     *
     * @throws \InvalidArgumentException If the given $row is not valid
     *
     * @return array
     */
    public function __invoke(array $row)
    {
        if (self::NULL_HANDLING_DISABLED == $this->null_handling_mode) {
            return $row;
        }

        array_walk($row, function ($value) {
            if (! $this->isConvertibleContent($value)) {
                throw new InvalidArgumentException('The values are not convertible into strings');
            }
        });

        if (self::NULL_AS_EMPTY == $this->null_handling_mode) {
            return str_replace(null, '', $row);
        }

        return array_filter($row, function ($value) {
            return ! is_null($value);
        });
    }

    /**
     * Check if a given value can be added into a CSV cell
     *
     * The value MUST respect the null handling mode
     * The value MUST be convertible into a string
     *
     * @param string|null $value the value to be added
     *
     * @return bool
     */
    private function isConvertibleContent($value)
    {
        return (is_null($value) && self::NULL_AS_EXCEPTION != $this->null_handling_mode)
            || \League\Csv\Writer::isValidString($value);
    }
}
