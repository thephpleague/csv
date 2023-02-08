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
use League\Csv\Reader;
use League\Csv\Statement;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('reader')]
final class RecordCollectionTest extends TestCase
{
    private Reader $csv;

    protected function setUp(): void
    {
        $this->csv = Reader::createFromPath(dirname(__DIR__, 2).'/test_files/prenoms.csv');
        $this->csv->setDelimiter(';');
        $this->csv->setHeaderOffset(0);
    }

    public function testConstructorWithReader(): void
    {
        self::assertCount(10121, new RecordCollection($this->csv));
    }

    public function testDoInitialize(): void
    {
        $result = Statement::create()
            ->offset(10)
            ->limit(15)
            ->process($this->csv);

        self::assertCount(15, new RecordCollection($result));
    }

    public function testMatching(): void
    {
        /** @var resource $fp */
        $fp = tmpfile();
        fputcsv($fp, ['field 1', 'field 2', 'field 3']);
        fputcsv($fp, ['foo', 'bar', 'baz']);
        fputcsv($fp, ['foofoo', 'barbar', 'bazbaz']);
        $csv = Reader::createFromStream($fp);
        $csv->setHeaderOffset(0);
        $collection = new RecordCollection($csv);

        self::assertSame([
            1 => ['field 1' => 'foo', 'field 2' => 'bar', 'field 3' => 'baz'],
            2 => ['field 1' => 'foofoo', 'field 2' => 'barbar', 'field 3' => 'bazbaz'],
        ], $collection->matching(new Criteria(null, ['field 1' => Criteria::ASC]))->toArray());

        $csv = null;
        fclose($fp);
    }
}
