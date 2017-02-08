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

/**
 * League CSV Record Formatter Interface
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
interface RecordValidatorInterface
{
    /**
     * Validate a CSV record
     *
     * The method tells whether the submtitted row is satisfying
     * the methods rules.
     *
     * @param string[] $record
     *
     * @return bool
     */
    public function validate(array $record): bool;
}
