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

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtoupper;
use function trim;

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
            'EQ', 'IS', 'EQUAL', 'EQUALS' => self::Equals,
            'GT', 'GREATER THAN' => self::GreaterThan,
            'GTE', 'GREATER THAN OR EQUAL' => self::GreaterThanOrEqual,
            'LT', 'LESSER THAN' => self::LesserThan,
            'LTE', 'LESSER THAN OR EQUAL' => self::LesserThanOrEqual,
            'NOT_REGEXP', 'NOT REGEXP' => self::NotRegexp,
            'NOT_CONTAIN', 'NOT CONTAIN', 'DOES_NOT_CONTAIN', 'DOES NOT CONTAIN' => self::NotContain,
            'NOT_IN', 'NOT IN' => self::NotIn,
            'NOT_BETWEEN', 'NOT BETWEEN' => self::Between,
            'STARTS WITH', 'START WITH' => self::StartsWith,
            'ENDS WITH', 'END WITH' => self::EndsWith,
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

    public function compare(mixed $first, mixed $second): bool
    {
        return match ($this) {
            self::Equals => is_scalar($first) ? $first === $second : $first == $second,
            self::NotEquals => is_scalar($first) ? $first !== $second : $first != $second,
            self::GreaterThan => $first > $second,
            self::GreaterThanOrEqual => $first >= $second,
            self::LesserThan => $first < $second,
            self::LesserThanOrEqual => $first <= $second,
            self::Between => (is_array($second) && array_is_list($second) && 2 === count($second)) ? $first >= $second[0] && $first <= $second[1] : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be an list containing 2 values, the minimum and maximum values.'),
            self::NotBetween => (is_array($second) && array_is_list($second) && 2 === count($second)) ? $first < $second[0] || $first > $second[1] : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be an list containing 2 values, the minimum and maximum values.'),
            self::Regexp => is_string($second) ? (is_string($first) && 1 === preg_match($second, $first)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::NotRegexp => is_string($second) ? (is_string($first) && 1 !== preg_match($second, $first)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::In => is_array($second) ? in_array($first, $second, is_scalar($first)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be an array.'), /* @phpstan-ignore-line */
            self::NotIn => is_array($second) ? !in_array($first, $second, is_scalar($first)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be an array.'), /* @phpstan-ignore-line */
            self::Contains => is_string($second) ? (is_string($first) && str_contains($first, $second)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::NotContain => is_string($second) ? (is_string($first) && !str_contains($first, $second)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::StartsWith => is_string($second) ? (is_string($first) && str_starts_with($first, $second)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::EndsWith => is_string($second) ? (is_string($first) && str_ends_with($first, $second)) : throw new InvalidArgument('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
        };
    }
}
