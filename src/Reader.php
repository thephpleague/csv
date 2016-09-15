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
 * Class to access records from a Csv document
 *
 * @package League.csv
 * @since  3.0.0
 *
 * @method array fetchAll() returns a sequential array of all records
 * @method array fetchOne(int $offset = 0) returns a specific record according to its offset
 * @method array fetchColumn(string|int $column_index = 0) returns a single specific column
 * @method array fetchPairs(string|int $offset_index = 0, string|int $value_index = 1) returns two CSV column as data pairs
 * @method string toHTML(string $class_attr = 'table-csv-data') returns a HTML table representation of the CSV document
 * @method DOMDocument toXML(string $root_name = 'csv', string $row_name = 'row', string $cell_name = 'cell') returns a XML representation of the CSV
 * @method array jsonSerialize() returns the records that should be serialized to JSON
 * @method int count() returns the records count from the CSV document
 */
class Reader extends AbstractCsv implements JsonSerializable, Countable
{
    /**
     * Stream filtering mode
     *
     * @var int
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns a collection of selected records
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
     * Triggered when invoking inaccessible methods in the object
     *
     * @param string $method
     * @param array  $args
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
     * Returns the records count
     *
     * @var int
     */
    public function count()
    {
        return $this->__call('count', []);
    }

    /**
     * records representation to be serialized to JSON
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->__call('jsonSerialize', []);
    }
}
