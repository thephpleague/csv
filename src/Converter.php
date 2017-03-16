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

use Traversable;

/**
 * An Interface to enable Converting a record collection
 * into another format
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
interface Converter
{
    /**
     * Convert an Record collection into another format.
     * The record collection must be an array or a Traversable object
     *
     * The return type depends on the output format provided by the
     * implementing class
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return mixed
     */
    public function convert($records);
}
