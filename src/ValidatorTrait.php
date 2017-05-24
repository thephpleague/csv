<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\OutOfRangeException;
use Traversable;
use TypeError;

/**
 *  A trait to validate properties
 *
 * @package  League.csv
 * @since    9.0.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal Use to validate incoming data
 */
trait ValidatorTrait
{
    /**
     * Validate an integer minimal range
     *
     * @param int    $value
     * @param int    $min_value
     * @param string $error_message
     *
     * @throws OutOfRangeException If the value is invalid
     *
     * @return int
     */
    protected function filterMinRange(int $value, int $min_value, string $error_message): int
    {
        if ($value >= $min_value) {
            return $value;
        }

        throw new OutOfRangeException(sprintf($error_message, $value));
    }

    /**
     * Validate the argument given is an iterable
     *
     * @param array|Traversable $iterable
     *
     * @throws TypeError If the submitted value is not iterable
     *
     * @return array|Traversable
     */
    protected function filterIterable($iterable)
    {
        if (is_array($iterable) || $iterable instanceof Traversable) {
            return $iterable;
        }

        throw new TypeError(sprintf('Argument passed must be iterable, %s given', gettype($iterable)));
    }

    /**
     * Filter Csv control character
     *
     * @param string $char Csv control character
     * @param string $type Csv control character type
     *
     * @throws InvalidArgumentException If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type): string
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('%s must be a single character', $type));
    }
}
