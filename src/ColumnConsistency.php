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
 *  A class to manage column consistency on data insertion into a CSV
 *
 * @package League.csv
 * @since   7.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 */
class ColumnConsistency
{
    use ValidatorTrait;

    /**
     * The number of column per record
     *
     * @var int
     */
    protected $columns_count = -1;

    /**
     * should the class detect the column count based on the inserted record
     *
     * @var bool
     */
    protected $detect_columns_count = false;

    /**
     * Set Inserted record column count
     *
     * @param int $value
     *
     * @return self
     */
    public function columnsCount(int $value): self
    {
        $clone = clone $this;
        $clone->columns_count = $this->filterMinRange($value, 0, 'The column count must be greater or equal to 0');
        $clone->detect_columns_count = false;

        return $clone;
    }

    /**
     * The method will set the $columns_count property
     * according to the next inserted record and therefore
     * will also validate it.
     *
     * @return self
     */
    public function autodetect(): self
    {
        $clone = clone $this;
        $clone->detect_columns_count = true;
        $clone->columns_count = -1;

        return $clone;
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
        if ($this->detect_columns_count) {
            $this->detect_columns_count = false;
            $this->columns_count = $count;

            return true;
        }

        return $count === $this->columns_count;
    }
}
