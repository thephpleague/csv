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

namespace League\Csv\Exception;

/**
 * Thrown when a data is not added to the Csv Document
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class InsertionException extends RuntimeException
{
    /**
     * The record submitted for insertion
     *
     * @var array
     */
    protected $data;

    /**
     * Validator which did not validated the data
     *
     * @var string
     */
    protected $name = '';

    /**
     * Create an Exception from a Record row
     *
     * @param string[] $record
     *
     * @return self
     */
    public static function createFromCsv(array $record): self
    {
        $exception = new static('Unable to write data to the CSV document');
        $exception->data = $record;

        return $exception;
    }

    /**
     * Create an Exception from a Record row
     *
     * @param string   $name validator name
     * @param string[] $data invalid  data
     *
     * @return self
     */
    public static function createFromValidator(string $name, array $data): self
    {
        $exception = new static('row validation failed');
        $exception->name = $name;
        $exception->data = $data;

        return $exception;
    }

    /**
     * return the validator name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * return the invalid data submitted
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
