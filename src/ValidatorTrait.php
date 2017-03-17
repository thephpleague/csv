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

use DOMAttr;
use DOMElement;
use DOMException;
use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\OutOfRangeException;
use Traversable;

/**
 *  A trait to validate properties
 *
 * @package  League.csv
 * @since    9.0.0
 * @author   Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal Use to validate incoming data
 */
trait ValidatorTrait
{
    /**
     * Filter Csv control character
     *
     * @param string $char   Csv control character
     * @param string $type   Csv control character type
     * @param string $method Method call
     *
     * @throws InvalidArgumentException If the Csv control character is not one character only.
     *
     * @return string
     */
    protected function filterControl(string $char, string $type, string $method)
    {
        if (1 == strlen($char)) {
            return $char;
        }

        throw new InvalidArgumentException(sprintf('%s: %s must be a single character', $method, $type));
    }

    /**
     * Validate an integer
     *
     * @param int    $value
     * @param int    $min_value
     * @param string $error_message
     *
     * @throws InvalidArgumentException If the value is invalid
     *
     * @return int
     */
    protected function filterInteger(int $value, int $min_value, string $error_message): int
    {
        if ($value >= $min_value) {
            return $value;
        }

        throw new OutOfRangeException($error_message);
    }

    /**
     * Validates the array to be used by the fetchAssoc method
     *
     * @param array $keys
     *
     * @throws InvalidArgumentException If the submitted array fails the assertion
     *
     * @return array
     */
    protected function filterColumnNames(array $keys): array
    {
        if (empty($keys) || $keys === array_unique(array_filter($keys, 'is_string'))) {
            return $keys;
        }

        throw new InvalidArgumentException('Use a flat array with unique string values');
    }

    /**
     * Validate the argument given is an iterable
     *
     * @param array|Traversable $iterable
     * @param string            $method
     *
     * @throws InvalidArgumentException If the submitted value is not iterable
     *
     * @return array|Traversable
     */
    protected function filterIterable($iterable, string $method)
    {
        if (is_array($iterable) || $iterable instanceof Traversable) {
            return $iterable;
        }

        throw new InvalidArgumentException(sprintf('Argument 1 passed to %s must be iterable, %s given', $method, is_object($iterable) ? get_class($iterable) : gettype($iterable)));
    }

    /**
     * Filter XML element name
     *
     * @param string $value Element name
     *
     * @throws DOMException If the Element name is invalid
     *
     * @return string
     */
    protected function filterNodeName(string $value): string
    {
        new DOMElement($value);

        return $value;
    }

    /**
     * Filter XML attribute name
     *
     * @param string $value Element name
     *
     * @throws DOMException If the Element attribute name is invalid
     *
     * @return string
     */
    protected function filterAttributeName(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        new DOMAttr($value);

        return $value;
    }
}
