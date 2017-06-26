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

use League\Csv\Exception\LengthException;
use League\Csv\Exception\OutOfRangeException;
use TypeError;

/**
 * A trait to validate input variables
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
     * @param string $caller public API method calling the method
     *
     * @throws LengthException If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type, string $caller): string
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new LengthException(sprintf('%s() expects %s to be a single character %s given', $caller, $type, $char));
    }

    /**
     * Filter Nullable Integer with mininal range check
     *
     * @see https://wiki.php.net/rfc/nullable_types
     *
     * @param int|null $value
     * @param int      $min_range
     * @param string   $error_message
     *
     * @throws TypError            if value is not a integer or null
     * @throws OutOfRangeException if value is not in a valid int range
     */
    protected function filterNullableInteger($value, int $min_range, string $error_message)
    {
        if (null === $value) {
            return;
        }

        if (!is_int($value)) {
            throw new TypeError(sprintf('Expected argument to be null or a integer %s given', gettype($value)));
        }

        if ($value < $min_range) {
            throw new OutOfRangeException($error_message);
        }
    }
}
