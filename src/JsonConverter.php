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

use League\Csv\Exception\RuntimeException;
use Traversable;

/**
 * A class to convert CSV records into a Json string
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class JsonConverter
{
    use ValidatorTrait;

    /**
     * Convert an Record collection into a Json string
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return string
     */
    public function convert($records, int $options = 0): string
    {
        $options = $this->filterMinRange($options, 0, 'The options must be a positive integer or 0');
        $records = $this->filterIterable($records);
        if (!is_array($records)) {
            $records = iterator_to_array($records);
        }

        $json = @json_encode($records, $options, 2);
        if (JSON_ERROR_NONE === json_last_error()) {
            return $json;
        }

        throw new RuntimeException(json_last_error_msg());
    }
}
