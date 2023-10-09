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

final class TabularDataReaderResultSetTest extends TabularDataReaderTestCase
{
    protected function tabularData(): TabularDataReader
    {
        return new ResultSet([
            ['date', 'temperature', 'place'],
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        ]);
    }

    protected function tabularDataWithHeader(): TabularDataReader
    {
        return new ResultSet([
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        ], ['date', 'temperature', 'place']);
    }
}
