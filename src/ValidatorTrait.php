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
     * Filter Nullable Integer
     *
     * @see https://wiki.php.net/rfc/nullable_types
     *
     * @param int|null $value
     *
     * @throws TypError if value is not a integer or null
     */
    protected function filterNullableInteger($value)
    {
        if (null !== $value && !is_int($value)) {
            throw new TypeError(sprintf('Expected argument to be null or a integer %s given', gettype($value)));
        }

        return $value;
    }
}
