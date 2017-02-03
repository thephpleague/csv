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

use League\Csv\Exception;

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
     * Filter Csv control character
     *
     * @param string $char Csv control character
     * @param string $type Csv control character type
     *
     * @throws Exception If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new Exception(sprintf('The %s must be a single character', $type));
    }

    /**
     * Validate an integer
     *
     * @param int    $value
     * @param int    $min_value
     * @param string $error_message
     *
     * @throws Exception If the value is invalid
     *
     * @return int
     */
    protected function filterInteger(int $value, int $min_value, string $error_message): int
    {
        if ($value < $min_value) {
            throw new Exception($error_message);
        }

        return $value;
    }

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @throws Exception If the submitted array fails the assertion
     *
     * @return array
     */
    protected function filterHeader(array $keys): array
    {
        if (empty($keys)) {
            return $keys;
        }

        if ($keys !== array_unique(array_filter($keys, [$this, 'isValidKey']))) {
            throw new Exception('Use a flat array with unique string values');
        }

        return $keys;
    }

    /**
     * Returns whether the submitted value can be used as string
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isValidKey(string $value)
    {
        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * Strip the BOM sequence from a record
     *
     * @param string[] $row
     * @param int      $bom_length
     * @param string   $enclosure
     *
     * @return string[]
     */
    protected function removeBOM(array $row, int $bom_length, string $enclosure): array
    {
        if (0 == $bom_length) {
            return $row;
        }

        $row[0] = mb_substr($row[0], $bom_length);
        if ($enclosure == mb_substr($row[0], 0, 1) && $enclosure == mb_substr($row[0], -1, 1)) {
            $row[0] = mb_substr($row[0], 1, -1);
        }

        return $row;
    }
}
