<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.2.3
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Plugin;

/**
 *  A class to validate null value handling on data insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class ForbiddenNullValuesValidator
{
    /**
     * Is the submitted row valid
     *
     * @param array $row
     *
     * @return bool
     */
    public function __invoke(array $row)
    {
        $res = array_filter($row, function ($value) {
            return null === $value;
        });

        return !$res;
    }
}
