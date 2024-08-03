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

use ReflectionException;
use League\Csv\Fragment\Expression;
use League\Csv\Fragment\Selection;
use League\Csv\Fragment\Type;

use function array_filter;
use function array_map;
use function array_reduce;

class FragmentFinder
{
    public static function create(): self
    {
        return new self();
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return iterable<int, TabularDataReader>
     */
    public function findAll(string $expression, TabularDataReader $tabularDataReader): iterable
    {
        return $this->find(Expression::tryFrom($expression), $tabularDataReader);
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function findFirst(string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        $fragment = $this->find(Expression::tryFrom($expression), $tabularDataReader)[0];

        return match ([]) {
            $fragment->first() => null,
            default => $fragment,
        };
    }

    /**
     * @throws Exception
     * @throws FragmentNotFound
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function findFirstOrFail(string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        $parsedExpression = Expression::tryFrom($expression);
        if ([] !== array_filter(
            $parsedExpression->selections,
            fn (Selection $selection): bool => -1 === $selection->rowStart && -1 === $selection->columnStart)
        ) {
            throw new FragmentNotFound('The expression `'.$expression.'` contains an invalid or an unsupported selection for the tabular data.');
        }

        $fragment = $this->find($parsedExpression, $tabularDataReader)[0];

        return match ([]) {
            $fragment->first() => throw new FragmentNotFound('No fragment found in the tabular data with the expression `'.$expression.'`.'),
            default => $fragment,
        };
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return array<int, TabularDataReader>
     */
    private function find(Expression $expression, TabularDataReader $tabularDataReader): array
    {
        return match ($expression->type) {
            Type::Row => $this->findByRow($expression, $tabularDataReader),
            Type::Column => $this->findByColumn($expression, $tabularDataReader),
            Type::Cell => $this->findByCell($expression, $tabularDataReader),
            Type::Unknown => [ResultSet::createFromRecords()],
        };
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws ReflectionException
     *
     * @return array<TabularDataReader>
     */
    private function findByRow(Expression $expression, TabularDataReader $tabularDataReader): array
    {
        $selections = array_filter($expression->selections, fn (Selection $selection): bool => -1 < $selection->rowStart);
        if ([] === $selections) {
            return [ResultSet::createFromRecords()];
        }

        $rowFilter = fn(array $record, int $offset): bool => [] !== array_filter(
                $selections,
                fn(Selection $selection) => $offset >= $selection->rowStart &&
                    (null === $selection->rowEnd || $offset <= $selection->rowEnd)
            );

        return [Statement::create()->where($rowFilter)->process($tabularDataReader)];
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return array<TabularDataReader>
     */
    private function findByColumn(Expression $expression, TabularDataReader $tabularDataReader): array
    {
        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->first();
        }

        $nbColumns = count($header);
        $selections = array_filter($expression->selections, fn(Selection $selection) => -1 < $selection->columnStart);
        if ([] === $selections) {
            return [ResultSet::createFromRecords()];
        }

        /** @var array<int> $columns */
        $columns = array_reduce(
            $selections,
            fn (array $columns, Selection $selection): array => [
                ...$columns,
                ...match (($columnRange = $selection->columnRange())) {
                    null => range($selection->columnStart, $nbColumns - 1),
                    default => $selection->columnEnd > $nbColumns || $selection->columnEnd === -1 ? range($selection->columnStart, $nbColumns - 1) : $columnRange,
                }
            ],
            []
        );

        return [match ([]) {
            $columns => ResultSet::createFromRecords(),
            default => Statement::create()->select(...$columns)->process($tabularDataReader),
        }];
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return array<TabularDataReader>
     */
    private function findByCell(Expression $expression, TabularDataReader $tabularDataReader): array
    {
        $header = $tabularDataReader->getHeader();
        if ([] === $header) {
            $header = $tabularDataReader->first();
        }

        $nbColumns = count($header);
        $selections = array_filter(
            $expression->selections,
            fn(Selection $selection) => -1 < $selection->rowStart && -1 < $selection->columnStart
        );
        if ([] === $selections) {
            return [ResultSet::createFromRecords()];
        }

        return array_map(
            fn (Selection $selection): TabularDataReader => Statement::create()
                ->where(
                    fn (array $record, int $offset): bool => $offset >= $selection->rowStart &&
                        (null === $selection->rowEnd || $offset <= $selection->rowEnd)
                )
                ->select(
                    ...match (($columnRange = $selection->columnRange())) {
                        null => range($selection->columnStart, $nbColumns - 1),
                        default => $selection->columnEnd > $nbColumns || $selection->columnEnd === -1 ? range($selection->columnStart, $nbColumns - 1) : $columnRange,
                    }
                )
                ->process($tabularDataReader),
            $selections
        );
    }
}
