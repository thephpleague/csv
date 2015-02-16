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
 *  Trait to validate the row before insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
trait Validator
{
    /**
     * The last failed validator
     *
     * @var string
     */
    protected $lastValidator;

    /**
     * The last failed row
     *
     * @var array|null
     */
    protected $lastRowData;

    /**
     * Callables to validate the row before insertion
     *
     * @var callable[]
     */
    protected $validationRules = [];

    /**
     * add a Validator to the collection
     *
     * @param callable $callable
     * @param string rule name
     *
     * @return static
     */
    public function addValidator(callable $callable, $name)
    {
        $this->validationRules[(string) $name] = $callable;

        return $this;
    }

    /**
     * Remove a validator from the collection
     *
     * @param callable $callable
     *
     * @return static
     */
    public function removeValidator($name)
    {
        if (array_key_exists($name, $this->validationRules)) {
            unset($this->validationRules[$name]);
        }

        return $this;
    }

    /**
     * Detect if a validator is already registered
     *
     * @param callable $callable
     *
     * @return bool
     */
    public function hasValidator($name)
    {
        return array_key_exists($name, $this->validationRules);
    }

    /**
     * Remove all registered validatior
     *
     * @return static
     */
    public function clearValidators()
    {
        $this->validationRules = [];

        return $this;
    }

    /**
    * validate a row
    *
    * @param array $row
    *
    * @return array
    */
    protected function validateRow(array $row)
    {
        $this->lastValidator = null;
        $this->lastRowData   = null;
        if (! $this->validationRules) {
            return true;
        }

        foreach ($this->validationRules as $name => $validator) {
            if (! $validator($row)) {
                $this->lastValidator = $name;
                $this->lastRowData   = $row;
                return false;
            }
        }

        return true;
    }

    /**
     * Return the name of the last validation failed rule
     *
     * @return string
     */
    public function getLastValidatorErrorName()
    {
        return $this->lastValidator;
    }

    /**
     * Returns the last failed data
     *
     * @return array|null
     */
    public function getLastValidatorErrorData()
    {
        return $this->lastRowData;
    }
}
