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

namespace League\Csv\Plugin;

/**
 *  A class to validate null value handling on data insertion into a CSV
 *
 * @package League.csv
 * @since   7.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class ForbiddenNullValuesValidator
{
    /**
     * Is the submitted record valid
     *
     * @param array $record
     *
     * @return bool
     */
    public function __invoke(array $record): bool
    {
        $res = array_filter($record, function ($value): bool {
            return null === $value;
        });

        return !$res;
    }
}
