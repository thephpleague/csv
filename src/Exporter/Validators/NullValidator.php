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
namespace League\Csv\Exporter\Validators;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class NullValidator
{
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
        $res = array_filter($row, function ($value) {
            return is_null($value);
        });

        return count($res) == 0;
    }
}
