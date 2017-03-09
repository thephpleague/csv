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

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package  League.csv
 * @since    9.0.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal Use to validate incoming data
 */
trait ValidatorTrait
{
    /**
     * Filter Csv control character
     *
     * @param string $char   Csv control character
     * @param string $type   Csv control character type
     * @param string $method Method call
     *
     * @throws InvalidArgumentException If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type, string $method)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('%s: %s must be a single character', $method, $type));
    }

    /**
     * Validate an integer
     *
     * @param int    $value
     * @param int    $min_value
     * @param string $error_message
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    protected function filterInteger(int $value, int $min_value, string $error_message): int
    {
        if ($value < $min_value) {
            throw new OutOfRangeException($error_message);
        }

        return $value;
    }

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return array
     */
    protected function filterColumnNames(array $keys): array
    {
        if (empty($keys)) {
            return $keys;
        }

        if ($keys !== array_unique(array_filter($keys, 'is_string'))) {
            throw new InvalidArgumentException('Use a flat array with unique string values');
        }

        return $keys;
    }
}
