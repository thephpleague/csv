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

/**
 * A class to interact with a CSV document without overriding it
 *
 * @package League.csv
 * @since  3.0.0
 *
 */
class Reader extends AbstractCsv
{
    /**
     * @inheritdoc
     */
    protected $stream_filter_mode = STREAM_FILTER_READ;

    /**
     * Returns the Record object
     *
     * @param Statement|null $stmt
     *
     * @return RecordSet
     */
    public function select(Statement $stmt = null)
    {
        $stmt = $stmt ?: new Statement();

        return $stmt->process($this);
    }
}
