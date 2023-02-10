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

namespace League\Csv\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use League\Csv\Statement;
use function array_reverse;

final class CriteriaConverter
{
    /**
     * Returns the Statement object created from the current Criteria object.
     *
     * This method MUST retain the state of the Statement instance, and return
     * a new Statement instance with the updated criteria.
     */
    public static function convert(Criteria $criteria, Statement $stmt = new Statement()): Statement
    {
        $stmt = self::addWhere($criteria, $stmt);
        $stmt = self::addOrderBy($criteria, $stmt);

        return self::addInterval($criteria, $stmt);
    }

    /**
     * Returns a Statement instance with the Criteria::getWhereExpression filter.
     *
     * This method MUST retain the state of the Statement instance, and return
     * a new Statement instance with the added Criteria::getWhereExpression filter.
     */
    public static function addWhere(Criteria $criteria, Statement $stmt = new Statement()): Statement
    {
        $expr = $criteria->getWhereExpression();
        if (null === $expr) {
            return $stmt;
        }

        /** @var callable $where */
        $where = (new ClosureExpressionVisitor())->dispatch($expr);

        return $stmt->where($where);
    }

    /**
     * Returns a Statement instance with the Criteria::getOrderings filter.
     *
     * This method MUST retain the state of the Statement instance, and return
     * a new Statement instance with the added Criteria::getOrderings filter.
     */
    public static function addOrderBy(Criteria $criteria, Statement $stmt = new Statement()): Statement
    {
        $next = null;
        foreach (array_reverse($criteria->getOrderings()) as $field => $ordering) {
            $next = ClosureExpressionVisitor::sortByField(
                $field,
                Criteria::DESC === $ordering ? -1 : 1,
                $next
            );
        }

        if (null === $next) {
            return $stmt;
        }

        return $stmt->orderBy($next);
    }

    /**
     * Returns a Statement instance with the Criteria interval parameters.
     *
     * This method MUST retain the state of the Statement instance, and return
     * a new Statement instance with the added Criteria::getFirstResult
     * and Criteria::getMaxResults filters parameters.
     */
    public static function addInterval(Criteria $criteria, Statement $stmt = new Statement()): Statement
    {
        return $stmt
            ->offset($criteria->getFirstResult() ?? 0)
            ->limit($criteria->getMaxResults() ?? -1);
    }
}
