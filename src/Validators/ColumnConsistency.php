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
namespace League\Csv\Validators;

use InvalidArgumentException;
use RuntimeException;

/**
 *  A class to manage data insertion into a CSV
 *
 * @package League.csv
 * @since  7.0.0
 *
 */
class ColumnConsistency
{
    /**
     * The number of column per row
     *
     * @var int
     */
    private $columns_count = -1;

    /**
     * should the class detect the column count based the inserted row
     *
     * @var bool
     */
    private $detect_columns_count = false;

    /**
     * Set Inserted row column count
     *
     * @param int $value
     *
     * @throws \InvalidArgumentException If $value is lesser than -1
     *
     * @return static
     */
    public function setColumnsCount($value)
    {
        if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => -1]])) {
            throw new InvalidArgumentException('the column count must an integer greater or equals to -1');
        }
        $this->detect_columns_count = false;
        $this->columns_count = $value;

        return $this;
    }

    /**
     * Column count getter
     *
     * @return int
     */
    public function getColumnsCount()
    {
        return $this->columns_count;
    }

    /**
     * The method will set the $columns_count property according to the next inserted row
     * and therefore will also validate the next line whatever length it has no matter
     * the current $columns_count property value.
     *
     * @return static
     */
    public function autodetectColumnsCount()
    {
        $this->detect_columns_count = true;

        return $this;
    }

    /**
     * Is the submitted row valid
     *
     * @param array $row
     *
     * @throws \RuntimeException If the given $row does not contain valid column count
     *
     * @return array
     */
    public function __invoke(array $row)
    {
        if (! $this->isColumnsCountConsistent($row)) {
            throw new RuntimeException('Adding '.count($row).' cells on a {$this->columns_count} cells per row CSV.');
        }

        return $row;
    }

    /**
     * Check column count consistency
     *
     * @param array $row the row to be added to the CSV
     *
     * @return bool
     */
    private function isColumnsCountConsistent(array $row)
    {
        if ($this->detect_columns_count) {
            $this->columns_count = count($row);
            $this->detect_columns_count = false;

            return true;
        } elseif (-1 == $this->columns_count) {
            return true;
        }

        return count($row) == $this->columns_count;
    }
}
