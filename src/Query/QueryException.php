<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv\Query;

use League\Csv\UnableToProcessCsv;
use Exception;

final class QueryException extends Exception implements UnableToProcessCsv
{
    public static function dueToUnknownColumn(string|int $column, array|object $value): self
    {
        return match (true) {
            is_object($value) => match (is_int($column)) {
                true => new self('The object property name can not be the integer`' . $column . '`.'),
                default => new self('The object property name `' . $column . '` could not be retrieved from the object.'),
            },
            default => match (is_string($column)) {
                true => new self('The column `' . $column . '` does not exist in the input array.'),
                default => new self('The column with the offset `' . $column . '` does not exist in the input array.'),
            },
        };
    }

    public static function dueToMissingColumn(): self
    {
        return new self('No valid column were found with the given data.');
    }

    public static function dueToUnknownOperator(string $operator): self
    {
        return new self('Unknown or unsupported comparison operator `'.$operator.'`');
    }
}
