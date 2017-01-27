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

namespace League\Csv\Config;

use InvalidArgumentException;

/**
 *  An abstract class to enable basic CSV manipulation
 *
 * @package League.csv
 * @since  9.0.0
 * @internal
 */
trait ValidatorTrait
{
    /**
     * validate a string
     *
     * @param mixed $str the value to evaluate as a string
     *
     * @throws InvalidArgumentException if the submitted data can not be converted to string
     *
     * @return string
     */
    protected static function filterString($str): string
    {
        if (is_string($str) || (is_object($str) && method_exists($str, '__toString'))) {
            return (string) $str;
        }
        throw new InvalidArgumentException('Expected data must be a string or stringable');
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
    protected function filterControl(string $char, string $type)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('The %s must be a single character', $type));
    }

    /**
     * Validate an integer
     *
     * @param int    $int
     * @param int    $minValue
     * @param string $errorMessage
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    protected function filterInteger(int $int, int $minValue, string $errorMessage): int
    {
        if ($int < $minValue) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $int;
    }
}
