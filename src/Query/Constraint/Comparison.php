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
        return self::tryFromOperator($operator) ?? throw QueryException::dueToUnknownOperator($operator);
    }

    /**
     * Values comparison.
     *
     * The method return true if the values satisfy the comparison operator, otherwise false is returned.
     *
     * @throws QueryException
     */
    public function compare(mixed $subject, mixed $reference): bool
    {
        $this->accept($reference);

        return match ($this) {
            self::Equals => self::isSingleValue($subject) ? $subject === $reference : $subject == $reference,
            self::NotEquals => self::isSingleValue($subject) ? $subject !== $reference : $subject != $reference,
            self::GreaterThan => $subject > $reference,
            self::GreaterThanOrEqual => $subject >= $reference,
            self::LesserThan => $subject < $reference,
            self::LesserThanOrEqual => $subject <= $reference,
            self::Between => $subject >= $reference[0] && $subject <= $reference[1], /* @phpstan-ignore-line */
            self::NotBetween => $subject < $reference[0] || $subject > $reference[1], /* @phpstan-ignore-line */
            self::In => in_array($subject, $reference, self::isSingleValue($subject)), /* @phpstan-ignore-line */
            self::NotIn => !in_array($subject, $reference, self::isSingleValue($subject)), /* @phpstan-ignore-line */
            self::Regexp => is_string($subject) && 1 === preg_match($reference, $subject), /* @phpstan-ignore-line */
            self::NotRegexp => is_string($subject) && 1 !== preg_match($reference, $subject), /* @phpstan-ignore-line */
            self::Contains => str_contains($subject, $reference), /* @phpstan-ignore-line */
            self::NotContain => is_string($subject) && !str_contains($subject, $reference), /* @phpstan-ignore-line */
            self::StartsWith => is_string($subject) && str_starts_with($subject, $reference), /* @phpstan-ignore-line */
            self::EndsWith => is_string($subject) && str_ends_with($subject, $reference), /* @phpstan-ignore-line */
        };
    }

    private static function isSingleValue(mixed $value): bool
    {
        return is_scalar($value) || null === $value;
    }

    /**
     * Assert if the reference value can be used with the Enum operator.
     *
     * @throws QueryException
     */
    public function accept(mixed $reference): void
    {
        match ($this) {
            self::Between,
            self::NotBetween => match (true) {
                !is_array($reference),
                !array_is_list($reference),
                2 !== count($reference) => throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an list containing 2 values, the minimum and maximum values.'),
                default => true,
            },
            self::In,
            self::NotIn => match (true) {
                !is_array($reference) => throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be an array.'),
                default => true,
            },
            self::Regexp,
            self::NotRegexp => match (true) {
                !is_string($reference),
                '' === $reference,
                false === @preg_match($reference, '') => throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a valid regular expression pattern string.'),
                default => true,
            },
            self::Contains,
            self::NotContain,
            self::StartsWith,
            self::EndsWith => match (true) {
                !is_string($reference),
                '' === $reference => throw new QueryException('The value used for comparison with the `'.$this->name.'` operator must be a non empty string.'),
                default => true,
            },
            self::Equals,
            self::NotEquals,
            self::GreaterThanOrEqual,
            self::GreaterThan,
            self::LesserThanOrEqual,
            self::LesserThan => true,
        };
    }
}
