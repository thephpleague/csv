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
namespace League\Csv;

use BadMethodCallException;
use Countable;
use JsonSerializable;

/**
 * A class to interact with a CSV document without overriding it
 *
 * @package League.csv
 * @since  3.0.0
 *
 * @method array fetchAll()
 * @method array fetchOne(int $offset = 0)
 * @method array fetchColumn(string|int $column_index = 0)
 * @method array fetchPairs(string|int $offset_index = 0, string|int $value_index = 1)
 * @method string toHTML(string $class_attr = 'table-csv-data')
 * @method DOMDocument toXML(string $root_name = 'csv', string $row_name = 'row', string $cell_name = 'cell')
 * @method array jsonSerialize()
 * @method int count()
 */
class Reader extends AbstractCsv implements JsonSerializable, Countable
{
    protected static $record_set_methods = [
        'fetchPairs' => 1,
        'fetchColumn' => 1,
        'fetchAll' => 1,
        'fetchOne' => 1,
        'toHTML' => 1,
        'toXML' => 1,
        'jsonSerialize' => 1,
        'count' => 1,
    ];

    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns the Record object
     *
     * @param Statement|null $stmt
     *
     * @return RecordSet
     */
    public function select(Statement $stmt = null)
    {
        $stmt = $stmt ?: new Statement();

        return $stmt->process($this);
    }

    /**
     * @inheritdoc
     */
    public function __call($method, array $args)
    {
        static $stmt;
        if (null === $stmt) {
            $stmt = new Statement();
        }

        if (isset(self::$record_set_methods[$method])) {
            $records = $stmt->process($this);

            return call_user_func_array([$records, $method], $args);
        }

        throw new BadMethodCallException(sprintf('Unknown method %s::%s', get_class($this), $method));
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return $this->__call('count', []);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->__call('jsonSerialize', []);
    }
}
