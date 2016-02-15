<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 8.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Plugin;

use InvalidArgumentException;

/**
 *  A class to manage column consistency on data insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class ColumnConsistencyValidator
{
    /**
     * The number of column per row
     *
     * @var int
     */
    private $columnsCount = -1;

    /**
     * should the class detect the column count based the inserted row
     *
     * @var bool
     */
    private $detectColumnsCount = false;

    /**
     * Set Inserted row column count
     *
     * @param int $value
     *
     * @throws InvalidArgumentException If $value is lesser than -1
     *
     */
    public function setColumnsCount($value)
    {
        if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the column count must an integer greater or equals to -1');
        }
        $this->detectColumnsCount = false;
        $this->columnsCount = $value;
    }

    /**
     * Column count getter
     *
     * @return int
     */
    public function getColumnsCount()
    {
        return $this->columnsCount;
    }

    /**
     * The method will set the $columnsCount property according to the next inserted row
     * and therefore will also validate the next line whatever length it has no matter
     * the current $columnsCount property value.
     *
     */
    public function autodetectColumnsCount()
    {
        $this->detectColumnsCount = true;
    }

    /**
     * Is the submitted row valid
     *
     * @param array $row
     *
     * @return bool
     */
    public function __invoke(array $row)
    {
        if ($this->detectColumnsCount) {
            $this->columnsCount = count($row);
            $this->detectColumnsCount = false;

            return true;
        }

        if (-1 == $this->columnsCount) {
            return true;
        }

        return count($row) == $this->columnsCount;
    }
}
