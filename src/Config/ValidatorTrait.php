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
            throw new InvalidArgumentException($error_message);
        }

        return $value;
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
