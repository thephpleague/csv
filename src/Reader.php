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
use ReflectionClass;
use ReflectionMethod;

/**
 * A class to access records from a Csv document
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

        return new RecordSet($this, $stmt);
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

        static $method_list;
        if (null === $method_list) {
            $method_list = array_flip(array_map(function (ReflectionMethod $method) {
                return $method->name;
            }, (new ReflectionClass(RecordSet::class))->getMethods(ReflectionMethod::IS_PUBLIC)));
        }

        if (isset($method_list[$method])) {
            return call_user_func_array([new RecordSet($this, $stmt), $method], $args);
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
