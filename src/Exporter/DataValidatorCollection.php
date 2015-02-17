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
trait DataValidatorCollection
{
    /**
     * Callables to validate the row before insertion
     *
     * @var callable[]
     */
    protected $validators = [];

    /**
     * add a Validator to the collection
     *
     * @param callable $callable
     * @param string   $name      the rule name
     *
     * @return $this
     */
    public function addValidator(callable $callable, $name)
    {
        $name = trim($name);
        $this->validators[$name] = $callable;

        return $this;
    }

    /**
     * Remove a validator from the collection
     *
     * @param string $name the validator name
     *
     * @return $this
     */
    public function removeValidator($name)
    {
        $name = trim($name);
        if (array_key_exists($name, $this->validators)) {
            unset($this->validators[$name]);
        }

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
        $name = trim($name);

        return array_key_exists($name, $this->validators);
    }

    /**
     * Remove all registered validatior
     *
     * @return $this
     */
    public function clearValidators()
    {
        $this->validators = [];

        return $this;
    }
}
