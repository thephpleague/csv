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

namespace League\Csv;

/**
 * A class to validate column consistency when inserting records into a CSV document
 *
 * @package League.csv
 * @since   7.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class ColumnConsistency
{
    /**
     * The number of column per record
     *
     * @var int
     */
    protected $columns_count;

    /**
     * New Instance
     *
     *
     * @param  int                 $columns_count
     * @throws OutOfRangeException if the column count is lesser than -1
     */
    public function __construct(int $columns_count = -1)
    {
        if ($columns_count < -1) {
            throw new Exception(sprintf('%s() expects the column count to be greater or equal to -1 %s given', __METHOD__, $columns_count));
        }

        $this->columns_count = $columns_count;
    }

    /**
     * Returns the column count
     *
     * @return int
     */
    public function getColumnCount(): int
    {
        return $this->columns_count;
    }

    /**
     * Tell whether the submitted record is valid
     *
     * @param array $record
     *
     * @return bool
     */
    public function __invoke(array $record): bool
    {
        $count = count($record);
        if (-1 === $this->columns_count) {
            $this->columns_count = $count;

            return true;
        }

        return $count === $this->columns_count;
    }
}
