<?php

namespace LeagueTest\Csv;

use League\Csv\Exception\InvalidArgumentException;
use League\Csv\StreamIterator;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileObject;

/**
 * @group csv
 * @coversDefaultClass League\Csv\StreamIterator
 */
class StreamIteratorTest extends TestCase
{
    /**
     * @covers ::__clone
     */
    public function testCloningIsForbidden()
    {
        $this->expectException(LogicException::class);
        $toto = clone new StreamIterator(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamIterator(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamIterator(fopen('php://stdin', 'r'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType()
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamIterator(curl_init());
    }

    /**
     * @covers ::fgets
     * @covers ::current
     */
    public function testIteratorWithLines()
    {
        $fp = fopen('php://temp', 'r+');
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
        ];

        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }

        $stream = new StreamIterator($fp);
        $stream->setFlags(SplFileObject::READ_AHEAD);
        $stream->rewind();
        $stream->current();
        $this->assertInternalType('string', $stream->fgets());
    }
}
