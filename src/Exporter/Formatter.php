<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 7.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Exporter;

/**
 *  A trait to format the row before insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
trait Formatter
{
    /**
     * Callables to format the row before insertion
     *
     * @var callable[]
     */
    protected $formatterRules = [];

    /**
     * add a formatter to the collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFormatter(callable $callable)
    {
        $this->formatterRules[] = $callable;

        return $this;
    }

    /**
     * Remove a formatter from the collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function removeFormatter(callable $callable)
    {
        $res = array_search($callable, $this->formatterRules, true);
        if (false !== $res) {
            unset($this->formatterRules[$res]);
        }

        return $this;
    }

    /**
     * Detect if the formatter is already registered
     *
     * @param callable $callable
     *
     * @return bool
     */
    public function hasFormatter(callable $callable)
    {
        return false !== array_search($callable, $this->formatterRules, true);
    }

    /**
     * Remove all registered formatter
     *
     * @return $this
     */
    public function clearFormatters()
    {
        $this->formatterRules = [];

        return $this;
    }

    /**
     * Format the given row
     *
     * @param array $row
     *
     * @return array
     */
    protected function formatRow($row)
    {
        if (! is_array($row)) {
            $row = str_getcsv($row, $this->delimiter, $this->enclosure, $this->escape);
        }

        foreach ($this->formatterRules as $formatter) {
            $row = (array) $formatter($row);
        }

        return (array) $row;
    }
}
