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
use Doctrine\Common\Collections\Expr\Comparison;
use League\Csv\Reader;
use League\Csv\ResultSet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('reader')]
final class CriteriaConverterTest extends TestCase
{
    private Reader $csv;

    protected function setUp(): void
    {
        $this->csv = Reader::createFromPath(dirname(__DIR__, 2).'/test_files/prenoms.csv');
        $this->csv->setDelimiter(';');
        $this->csv->setHeaderOffset(0);
    }

    public function testAdapter(): void
    {
        $expr = new Comparison('prenoms', '=', 'Adam');
        $criteria = new Criteria($expr, ['annee' => 'ASC'], 0, 5);

        $records = CriteriaConverter::convert($criteria)->process($this->csv);

        self::assertInstanceOf(ResultSet::class, $records);
        self::assertTrue(count($records) <= 5);
    }

    public function testAdapterWithoutExpression(): void
    {
        $criteria = new Criteria(null, ['annee' => 'ASC'], 0, 5);
        $records = CriteriaConverter::convert($criteria)->process($this->csv);

        self::assertInstanceOf(ResultSet::class, $records);
        self::assertCount(5, $records);
    }

    public function testAdapterWithoutOrdering(): void
    {
        $criteria = new Criteria(new Comparison('prenoms', '=', 'Adam'));

        self::assertInstanceOf(ResultSet::class, CriteriaConverter::convert($criteria)->process($this->csv));
    }

    public function testAdapterWithoutInterval(): void
    {
        $expr = new Comparison('prenoms', '=', 'Adam');
        $criteria = new Criteria($expr, ['annee' => 'ASC']);

        self::assertInstanceOf(ResultSet::class, CriteriaConverter::convert($criteria)->process($this->csv));
    }

    public function testAdapterWithEmptyCriteria(): void
    {
        self::assertInstanceOf(ResultSet::class, CriteriaConverter::convert(new Criteria())->process($this->csv));
    }
}
