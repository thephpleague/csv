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
 *  Defines constants for common BOM sequences
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
final class BOM
{
    /**
     *  UTF-8 BOM sequence
     */
    const UTF8 = "\xEF\xBB\xBF";

    /**
     * UTF-16 BE BOM sequence
     */
    const UTF16_BE = "\xFE\xFF";

    /**
     * UTF-16 LE BOM sequence
     */
    const UTF16_LE = "\xFF\xFE";

    /**
     * UTF-32 BE BOM sequence
     */
    const UTF32_BE = "\x00\x00\xFE\xFF";

    /**
     * UTF-32 LE BOM sequence
     */
    const UTF32_LE = "\xFF\xFE\x00\x00";

    /**
     * Returns all possible BOM sequences as an array
     *
     * @return string[]
     */
    private static function toArray(): array
    {
        static $cache;

        $cache = $cache ?? (new ReflectionClass(BOM::class))->getConstants();

        return $cache;
    }

    /**
     * Returns the BOM sequence found at the start of the string
     *
     * If no valid BOM sequence is found an empty string is returned
     *
     * @param string $str
     *
     * @return string
     */
    public static function match(string $str): string
    {
        foreach (self::toArray() as $sequence) {
            if (0 === strpos($str, $sequence)) {
                return $sequence;
            }
        }

        return '';
    }

    /**
     * Tell whether the submitted sequence is a valid BOM sequence
     *
     * @param string $sequence
     *
     * @return bool
     */
    public static function isValid(string $sequence): bool
    {
        return in_array($sequence, self::toArray(), true);
    }
}
