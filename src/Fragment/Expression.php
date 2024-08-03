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

namespace League\Csv\Fragment;

use League\Csv\FragmentNotFound;
use function preg_match;
use function explode;
use function array_map;

final class Expression
{
    private const REGEXP_URI_FRAGMENT = ',^(?<type>row|cell|col)=(?<selections>.*)$,i';

    /** @param array<Selection> $selections */
    private function __construct(
        public readonly Type $type,
        public readonly array $selections
    ) {}

    public static function tryFrom(string $expression): self
    {
        try {
            return self::from($expression);
        } catch (FragmentNotFound $fragmentNotFound) {
            return self::fromUnknown();
        }
    }

    public static function from(string $expression): self
    {
        if (1 !== preg_match(self::REGEXP_URI_FRAGMENT, $expression, $matches)) {
            throw new FragmentNotFound('The submitted expression `'.$expression.'` is invalid.');
        }

        $selections = explode(';', $matches['selections']);

        return match (Type::from(strtolower($matches['type']))) {
            Type::Row => self::fromRow(...$selections),
            Type::Column => self::fromColumn(...$selections),
            Type::Cell => self::fromCell(...$selections),
            default => throw new FragmentNotFound('The submitted expression `'.$expression.'` is invalid.'),
        };
    }

    public static function fromUnknown(): self
    {
        return new self(Type::Unknown, [Selection::fromUnknown()]);
    }

    public static function fromCell(string ...$selections): self
    {
        return new self(Type::Cell, array_map(Selection::fromCell(...), $selections));
    }

    public static function fromColumn(string ...$selections): self
    {
        return new self(Type::Column, array_map(Selection::fromColumn(...), $selections));
    }

    public static function fromRow(string ...$selections): self
    {
        return new self(Type::Row, array_map(Selection::fromRow(...), $selections));
    }
}
