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
 * A class to convert CSV records into a DOMDOcument object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class JsonConverter implements Converter
{
    use ConverterTrait;

    /**
     * Tell whether to preserve offset for json
     *
     * @var bool
     */
    protected $preserve_record_offset = false;

    /**
     * json_encode options
     *
     * @var int
     */
    protected $options = 0;

    /**
     * json_encode depth
     *
     * @var int
     */
    protected $depth = 512;

    /**
     * Whether we should preserve the CSV document record offset.
     *
     * If set to true CSV document record offset will added to
     * method output where it makes sense.
     *
     * @param bool $status
     *
     * @return static
     */
    public function preserveRecordOffset(bool $status)
    {
        $clone = clone $this;
        $clone->preserve_record_offset = $status;

        return $clone;
    }

    /**
     * Json encode Options
     *
     * @param int $options
     * @param int $depth
     *
     * @return self
     */
    public function options(int $options = 0, int $depth = 512): self
    {
        $clone = clone $this;
        $clone->options = $options;
        $clone->depth = $depth;

        return $clone;
    }

    /**
     * Convert an Record collection into a Json string
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return string
     */
    public function convert($records)
    {
        $records = $this->convertToUtf8($this->filterIterable($records, __METHOD__));
        if (!is_array($records)) {
            $records = iterator_to_array($records, $this->preserve_record_offset);
        } elseif (!$this->preserve_record_offset) {
            $records = array_values($records);
        }

        $json = @json_encode($records, $this->options, $this->depth);
        if (JSON_ERROR_NONE === json_last_error()) {
            return $json;
        }

        throw new RuntimeException(json_last_error_msg());
    }
}
