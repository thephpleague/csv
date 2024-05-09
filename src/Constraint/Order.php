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

namespace League\Csv\Constraint;

use League\Csv\InvalidArgument;

use function strtoupper;
use function trim;

enum Order: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';

    public static function tryFromOperator(string $operator): ?self
    {
        $operator = strtoupper(trim($operator));

        return match ($operator) {
            'ASC', 'ASCENDING', 'UP' => self::Ascending,
            'DESC', 'DESCENDING', 'DOWN' => self::Descending,
            default => self::tryFrom($operator),
        };
    }

    public static function fromOperator(string $operator): self
    {
        return self::tryFromOperator($operator) ?? throw new InvalidArgument('Unknown or unsupported order operator `'.$operator.'`');
    }
}
