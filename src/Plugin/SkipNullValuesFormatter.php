<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.2.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Plugin;

/**
 *  A class to remove null value from data before insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class SkipNullValuesFormatter
{
    /**
     * remove null value form the submitted array
     *
     * @param array $row
     *
     * @return array
     */
    public function __invoke(array $row)
    {
        return array_filter($row, function ($value) {
            return !is_null($value);
        });
    }
}
