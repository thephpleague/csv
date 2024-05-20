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

namespace League\Csv\Query\Constraint;

use League\Csv\Query\QueryException;

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
     * @throws QueryException
     */
    public static function fromOperator(string $operator): self
    {
        return self::tryFromOperator($operator) ?? throw new QueryException('Unknown or unsupported comparison operator `'.$operator.'`');
    }

    /**
     * @throws QueryException
     */
    public function compare(mixed $needle, mixed $haystack): bool
    {
        return match ($this) {
            self::Equals => self::isStrict($needle) ? $needle === $haystack : $needle == $haystack,
            self::NotEquals => self::isStrict($needle) ? $needle !== $haystack : $needle != $haystack,
            self::GreaterThan => $needle > $haystack,
            self::GreaterThanOrEqual => $needle >= $haystack,
            self::LesserThan => $needle < $haystack,
            self::LesserThanOrEqual => $needle <= $haystack,
            self::Between => (is_array($haystack) && array_is_list($haystack) && 2 === count($haystack)) ? $needle >= $haystack[0] && $needle <= $haystack[1] : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an list containing 2 values, the minimum and maximum values.'),
            self::NotBetween => (is_array($haystack) && array_is_list($haystack) && 2 === count($haystack)) ? $needle < $haystack[0] || $needle > $haystack[1] : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an list containing 2 values, the minimum and maximum values.'),
            self::Regexp => is_string($haystack) ? (is_string($needle) && 1 === preg_match($haystack, $needle)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::NotRegexp => is_string($haystack) ? (is_string($needle) && 1 !== preg_match($haystack, $needle)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::In => is_array($haystack) ? in_array($needle, $haystack, self::isStrict($needle)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an array.'), /* @phpstan-ignore-line */
            self::NotIn => is_array($haystack) ? !in_array($needle, $haystack, self::isStrict($needle)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an array.'), /* @phpstan-ignore-line */
            self::Contains => is_string($haystack) ? (is_string($needle) && str_contains($needle, $haystack)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::NotContain => is_string($haystack) ? (is_string($needle) && !str_contains($needle, $haystack)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::StartsWith => is_string($haystack) ? (is_string($needle) && str_starts_with($needle, $haystack)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
            self::EndsWith => is_string($haystack) ? (is_string($needle) && str_ends_with($needle, $haystack)) : throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a string.'),
        };
    }

    private static function isStrict(mixed $value): bool
    {
        return is_scalar($value) || null === $value;
    }

    public function accept(mixed $reference): bool
    {
        return match ($this) {
            self::Between,
            self::NotBetween => is_array($reference) && array_is_list($reference) && 2 === count($reference),
            self::In,
            self::NotIn => is_array($reference),
            self::Regexp,
            self::NotRegexp,
            self::Contains,
            self::NotContain,
            self::StartsWith,
            self::EndsWith => is_string($reference),
            default => true,
        };
    }
}
