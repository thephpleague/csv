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

namespace League\Csv\Plugin;

use League\Csv\ValidatorTrait;

/**
 *  A class to manage column consistency on data insertion into a CSV
 *
 * @package League.csv
 * @since   7.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class ColumnConsistencyValidator
{
    use ValidatorTrait;

    /**
     * The number of column per row
     *
     * @var int
     */
    protected $columns_count = -1;

    /**
     * should the class detect the column count based the inserted row
     *
     * @var bool
     */
    protected $detect_columns_count = false;

    /**
     * Set Inserted row column count
     *
     * @param int $value
     */
    public function setColumnsCount(int $value)
    {
        $this->detect_columns_count = false;
        $this->columns_count = $this->filterInteger($value, -1, __METHOD__.': the column count must be greater or equal to -1');
    }

    /**
     * Column count getter
     *
     * @return int
     */
    public function getColumnsCount(): int
    {
        return $this->columns_count;
    }

    /**
     * The method will set the $columns_count property according to the next inserted record
     * and therefore will also validate the next line whatever length it has no matter
     * the current $columns_count property value.
     */
    public function autodetectColumnsCount()
    {
        $this->detect_columns_count = true;
    }

    /**
     * Is the submitted row valid
     *
     * @param array $record
     *
     * @return bool
     */
    public function __invoke(array $record): bool
    {
        if ($this->detect_columns_count) {
            $this->setColumnsCount(count($record));

            return true;
        }

        if (-1 === $this->columns_count) {
            return true;
        }

        return count($record) === $this->columns_count;
    }
}
