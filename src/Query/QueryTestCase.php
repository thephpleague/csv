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

namespace League\Csv\Query;

use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\TestCase;

abstract class QueryTestCase extends TestCase
{
    protected readonly Reader $document;
    protected readonly Statement $stmt;
    protected readonly array $iterable;

    protected function setUp(): void
    {
        parent::setUp();

        $csv = <<<CSV
CustomerID,CustomerName,ContactName,Address,City,PostalCode,Country
1,Alfreds Futterkiste,Maria Anders,Obere Str. 57,Berlin,12209,Germany
2,Ana Trujillo Emparedados y helados,Ana Trujillo,Avda. de la Constitución 2222,México D.F.,05021,Mexico
3,Antonio Moreno Taquería,Antonio Moreno,Mataderos 2312,México D.F.,05023,Mexico
4,Around the Horn,Thomas Hardy,120 Hanover Sq.,London,WA1 1DP,UK
5,Berglunds snabbköp,Christina Berglund,Berguvsvägen 8,Luleå,S-958 22,Sweden
CSV;
        $this->document = Reader::createFromString($csv);
        $this->document->setHeaderOffset(0);
        $this->stmt = Statement::create();
        $this->iterable = [
            ['volume' => 67, 'edition' => 2],
            ['volume' => 86, 'edition' => 1],
            ['volume' => 85, 'edition' => 6],
            ['volume' => 98, 'edition' => 2],
            ['volume' => 86, 'edition' => 6],
            ['volume' => 67, 'edition' => 7],
        ];
    }
}
