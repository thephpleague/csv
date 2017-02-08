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

use League\Csv\RecordFormatterInterface as RecordFormatter;

/**
 * An Adapter Class to convert a callable
 * into a FormatterInterface implementing object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class CallableFormatterAdapter implements RecordFormatter
{
    /**
     * @var callable
     */
    protected $callable;

    /**
     * New instance
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @inheritdoc
     */
    public function format(array $record): array
    {
        return ($this->callable)($record);
    }
}
