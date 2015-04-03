<?php
/**
 * This file is part of the League.csv library
 *
 * @license http://opensource.org/licenses/MIT
 * @link https://github.com/thephpleague/csv/
 * @version 7.0.1
 * @package League.csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace League\Csv\Modifier;

/**
 *  An iterator to turn numerically indexed arrays into associative arrays.
 *
 * @package League.csv
 */
class AssociativeIterator extends \IteratorIterator
{
    /**
     * @var array
     */
    private $keys;

    /**
     * @param \Iterator $iterator
     * @param array $keys
     */
    public function __construct(\Iterator $iterator, array $keys)
    {
        $this->keys = $keys;
        parent::__construct($iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $row = parent::current();

        $keys_count = count($this->keys);
        if (count($row) !== $keys_count) {
            $row = array_slice(array_pad($row, $keys_count, null), 0, $keys_count);
        }

        return array_combine($this->keys, $row);
    }
}
