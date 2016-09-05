<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.1.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Exception;

use InvalidArgumentException;

/**
 *  Thrown when a data is not validated prior to insertion
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class InvalidRowException extends InvalidArgumentException
{
    /**
     * Validator which did not validated the data
     * @var string
     */
    private $name;

    /**
     * Validator Data which caused the error
     * @var array
     */
    private $data;

    /**
     * New Instance
     *
     * @param string $name    validator name
     * @param array  $data    invalid  data
     * @param string $message exception message
     */
    public function __construct($name, array $data = [], $message = '')
    {
        parent::__construct($message);
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * return the validator name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * return the invalid data submitted
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
