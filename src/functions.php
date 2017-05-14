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

use ReflectionClass;

/**
 * Returns the BOM sequence found at the start of the string
 *
 * If no valid BOM sequence is found an empty string is returned
 *
 * @param string $str
 *
 * @return string
 */
function bom_match(string $str): string
{
    static $list;

    $list = $list ?? (new ReflectionClass(ByteSequence::class))->getConstants();

    foreach ($list as $sequence) {
        if (0 === strpos($str, $sequence)) {
            return $sequence;
        }
    }

    return '';
}
