<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.2.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Modifier;

use League\Csv\Exception\InvalidRowException;

/**
 *  Trait to format and validate the row before insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
trait RowFilter
{
    /**
     * Callables to validate the row before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * Callables to format the row before insertion
     *
     * @var callable[]
     */
    protected $formatters = [];

    /**
     * add a formatter to the collection
     *
     * @param callable $callable
     *
     * @return $this
     */
    public function addFormatter(callable $callable)
    {
        $this->formatters[] = $callable;

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
        $res = array_search($callable, $this->formatters, true);
        unset($this->formatters[$res]);

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
        return false !== array_search($callable, $this->formatters, true);
    }

    /**
     * Remove all registered formatter
     *
     * @return $this
     */
    public function clearFormatters()
    {
        $this->formatters = [];

        return $this;
    }

    /**
     * add a Validator to the collection
     *
     * @param callable $callable
     * @param string   $name     the rule name
     *
     * @return $this
     */
    public function addValidator(callable $callable, $name)
    {
        $name = $this->validateString($name);

        $this->validators[$name] = $callable;

        return $this;
    }

    /**
     * @inheritdoc
     */
    abstract protected function validateString($str);

    /**
     * Remove a validator from the collection
     *
     * @param string $name the validator name
     *
     * @return $this
     */
    public function removeValidator($name)
    {
        $name = $this->validateString($name);
        unset($this->validators[$name]);

        return $this;
    }

    /**
     * Detect if a validator is already registered
     *
     * @param string $name the validator name
     *
     * @return bool
     */
    public function hasValidator($name)
    {
        $name = $this->validateString($name);

        return isset($this->validators[$name]);
    }

    /**
     * Remove all registered validators
     *
     * @return $this
     */
    public function clearValidators()
    {
        $this->validators = [];

        return $this;
    }

    /**
     * Format the given row
     *
     * @param array $row
     *
     * @return array
     */
    protected function formatRow(array $row)
    {
        foreach ($this->formatters as $formatter) {
            $row = call_user_func($formatter, $row);
        }

        return $row;
    }

    /**
    * Validate a row
    *
    * @param array $row
    *
    * @throws InvalidRowException If the validation failed
    */
    protected function validateRow(array $row)
    {
        foreach ($this->validators as $name => $validator) {
            if (true !== call_user_func($validator, $row)) {
                throw new InvalidRowException($name, $row, 'row validation failed');
            }
        }
    }
}
