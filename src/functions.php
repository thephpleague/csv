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
use Traversable;

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

/**
 * Detect Delimiters usage in a CSV object
 *
 * Returns a associative array where each key represents
 * a submitted delimiter and each value the number CSV fields found
 * when processing at most $limit CSV records with the given delimiter
 *
 * @param Reader   $csv        the CSV object
 * @param string[] $delimiters the delimiters to consider
 * @param int      $limit      Detection is made using up to $limit CSV records
 *
 * @return int[]
 */
function delimiter_detect(Reader $csv, array $delimiters, int $limit = 1): array
{
    $found = array_unique(array_filter($delimiters, function (string $value): bool {
        return 1 == strlen($value);
    }));
    $stmt = (new Statement())->limit($limit)->where(function (array $record): bool {
        return count($record) > 1;
    });
    $reducer = function (array $result, string $delimiter) use ($csv, $stmt): array {
        $result[$delimiter] = count($stmt->process($csv->setDelimiter($delimiter))->fetchAll(), COUNT_RECURSIVE);

        return $result;
    };
    $delimiter = $csv->getDelimiter();
    $header_offset = $csv->getHeaderOffset();
    $csv->setHeaderOffset(null);
    $stats = array_reduce($found, $reducer, array_fill_keys($delimiters, 0));
    $csv->setHeaderOffset($header_offset)->setDelimiter($delimiter);

    return $stats;
}


if (!function_exists('\is_iterable')) {
    /**
     * Tell whether the contents of the variable is iterable
     *
     * @see http://php.net/manual/en/function.is-iterable.php
     *
     * @param array|Traversable $iterable
     *
     * @return bool
     */
    function is_iterable($iterable): bool
    {
        return is_array($iterable) || $iterable instanceof Traversable;
    }
}
