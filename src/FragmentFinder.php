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
     */
    public function findFirst(Expression|string $expression, TabularDataReader $tabularDataReader): ?TabularDataReader
    {
        $tabularData = Expression::from($expression)->firstFragment($tabularDataReader);
        if ($tabularData instanceof TabularDataReader && [] !== $tabularData->first()) {
            return $tabularData;
        }

        return null;
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     */
    public function findFirstOrFail(Expression|string $expression, TabularDataReader $tabularDataReader): TabularDataReader
    {
        $parseExpression = !$expression instanceof Expression ? Expression::from($expression) : $expression;
        if ((string) $parseExpression !== strtolower((string) $expression)) {
            throw new FragmentNotFound('The expression "' . $expression . '" contains invalid section(s).');
        }

        return $this->findFirst($parseExpression, $tabularDataReader) ?? throw new FragmentNotFound('No fragment found in the tabular data with the expression `'.$expression.'`.');
    }

    /**
     * @throws Exception
     * @throws InvalidArgument
     * @throws ReflectionException
     * @throws SyntaxError
     *
     * @return iterable<TabularDataReader>
     * @see Expression::fragment()
     *
     * @codeCoverageIgnore
     * @deprecated since version 9.17.0
     */
    public function findAll(Expression|string $expression, TabularDataReader $tabularDataReader): iterable
    {
        $found = false;
        foreach (Expression::from($expression)->fragment($tabularDataReader) as $tabularData) {
            $found = true;
            yield $tabularData;
        }

        if (false === $found) {
            yield ResultSet::createFromRecords();
        }
    }
}
