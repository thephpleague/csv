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

enum Comparison: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LesserThan = '<';
    case LesserThanOrEqual = '<=';
    case Between = 'BETWEEN';
    case NotBetween = 'NBETWEEN';
    case Regexp = 'REGEXP';
    case NotRegexp = 'NREGEXP';
    case In = 'IN';
    case NotIn = 'NIN';
    case Contains = 'CONTAINS';
    case NotContain = 'NCONTAIN';
    case StartsWith = 'STARTS_WITH';
    case EndsWith = 'ENDS_WITH';

    public static function tryFromOperator(string $operator): ?self
    {
        $operator = strtoupper(trim($operator));

        return match ($operator) {
            '<>', 'NEQ', 'IS NOT', 'NOT EQUAL' => self::NotEquals,
            'EQ', 'IS', 'EQUAL' => self::Equals,
            'GT', 'GREATER THAN' => self::GreaterThan,
            'GTE', 'GREATER THAN OR EQUAL' => self::GreaterThanOrEqual,
            'LT', 'LESSER THAN' => self::LesserThan,
            'LTE', 'LESSER THAN OR EQUAL' => self::LesserThanOrEqual,
            'NOT_REGEXP', 'NOT REGEXP' => self::NotRegexp,
            'NOT_CONTAIN', 'NOT CONTAIN', 'DOES_NOT_CONTAIN', 'DOES NOT CONTAIN' => self::NotContain,
            'NOT_IN', 'NOT IN' => self::NotIn,
            'NOT_BETWEEN', 'NOT BETWEEN' => self::Between,
            default => self::tryFrom($operator),
        };
    }

    /**
     * @throws InvalidArgument
     */
    public static function fromOperator(string $operator): self
    {
        return self::tryFromOperator($operator) ?? throw new InvalidArgument('Unknown or unsupported comparison operator `'.$operator.'`');
    }
}
