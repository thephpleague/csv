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

use InvalidArgumentException;

/**
 *  Exception triggered by an invalid CSV record
 *
 * @package League.csv
 * @since  7.0.0
 */
class InvalidRowException extends InvalidArgumentException
{
    /**
     * Validator which did not validated the data
     *
     * @var string
     */
    private $name;

    /**
     * Validator Data which caused the error
     *
     * @var string[]
     */
    private $record;

    /**
     * New Instance
     *
     * @param string   $name    validator name
     * @param string[] $record  invalid  data
     * @param string   $message exception message
     */
    public function __construct($name, array $record = [], $message = '')
    {
        parent::__construct($message);
        $this->name = $name;
        $this->record = $record;
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
     * return the invalid record
     *
     * @return string[]
     */
    public function getData()
    {
        return $this->record;
    }
}
