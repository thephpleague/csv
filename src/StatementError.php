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

namespace League\Csv;

use RuntimeException;

final class StatementError extends RuntimeException implements UnableToProcessCsv
{
    public static function dueToUnknownColumn(string|int $column): self
    {
        return match (is_string($column)) {
            true => new self('The column `'.$column.'` does not exist in the tabular data document.'),
            default => new self('The column with the offset `'.$column.'` does not exist in the tabular data document.'),
        };
    }
}
