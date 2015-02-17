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
namespace League\Csv\Exception;

use InvalidArgumentException;

/**
 *  Thrown when a data is not validated prior to insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class ValidationException extends InvalidArgumentException
{
}
