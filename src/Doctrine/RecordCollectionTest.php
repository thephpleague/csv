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
use PHPUnit\Framework\TestCase;

final class RecordCollectionTest extends TestCase
{
    /**
     * @var Reader
     */
    protected $csv;

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
        fputcsv($fp, ['foo', 'bar', 'baz']);
        fputcsv($fp, ['foofoo', 'barbar', 'bazbaz']);
        $csv = Reader::createFromStream($fp);
        $collection = new RecordCollection($csv);

        self::assertSame([
            ['foo', 'bar', 'baz'],
            ['foofoo', 'barbar', 'bazbaz'],
        ], $collection->matching(new Criteria(null, [0 => Criteria::ASC]))->toArray());

        $csv = null;
        fclose($fp);
    }
}
