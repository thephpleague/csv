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
     * @return iterable<TabularDataReader>
     */
    public function findAll(Expression|string $expression, TabularDataReader $tabularDataReader): iterable
    {
        $found = false;
        foreach ($this->find($expression, $tabularDataReader) as $result) {
            $found = true;
            yield $result;
        }

        if (false === $found) {
            yield ResultSet::createFromRecords();
        }
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function findFirst(Expression|string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        foreach ($this->find($expression, $tabularDataReader) as $fragment) {
            if ($fragment->first() === []) {
                return null;
            }

            return $fragment;
        }

        return null;
    }

    /**
     * @throws Exception
     * @throws FragmentNotFound
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function findFirstOrFail(Expression|string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        return $this->findFirst($expression, $tabularDataReader) ?? throw new FragmentNotFound('No fragment found in the tabular data with the expression `'.$expression.'`.');
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return array<string, TabularDataReader>
     */
    private function find(Expression|string $expression, TabularDataReader $tabularDataReader): iterable
    {
        if (!$expression instanceof Expression) {
            try {
                $expression = Expression::from($expression);
            } catch (FragmentNotFound) {
                return;
            }
        }

        foreach ($expression->query($tabularDataReader) as $selection => $statement) {
            yield $selection => $statement->process($tabularDataReader);
        }
    }
}
