<?php

namespace LeagueTest\Csv;

use League\Csv\Exception\InvalidArgumentException;
use League\Csv\Exception\RuntimeException;
use League\Csv\StreamIterator;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;

/**
 * @group csv
 * @coversDefaultClass League\Csv\StreamIterator
 */
class StreamIteratorTest extends TestCase
{
    public function setUp()
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);
    }

    public function tearDown()
    {
        stream_wrapper_unregister(StreamWrapper::PROTOCOL);
    }

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
        $this->expectException(TypeError::class);
        new StreamIterator(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        $this->expectException(RuntimeException::class);
        new StreamIterator(fopen('php://stdin', 'r'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType()
    {
        $this->expectException(TypeError::class);
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

    /**
     * @covers ::createFromPath
     * @covers ::fgets
     * @covers ::current
     */
    public function testCreateStreamFromPathWithContext()
    {
        $fp = fopen('php://temp', 'r+');
        $expected = [
            ['john', 'doe', 'john.doe@example.com'],
            ['john', 'doe', 'john.doe@example.com'],
        ];

        foreach ($expected as $row) {
            fputcsv($fp, $row);
        }

        $stream = StreamIterator::createFromPath(
            StreamWrapper::PROTOCOL.'://stream',
            'r+',
            stream_context_create([StreamWrapper::PROTOCOL => ['stream' => $fp]])
        );
        $stream->setFlags(SplFileObject::READ_AHEAD);
        $stream->rewind();
        $stream->current();
        $this->assertInternalType('string', $stream->fgets());
    }


    /**
     * @covers ::fputcsv
     * @dataProvider fputcsvProvider
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function testfputcsv($delimiter, $enclosure, $escape)
    {
        $this->expectException(InvalidArgumentException::class);
        $stream = new StreamIterator(fopen('php://temp', 'r+'));
        $stream->fputcsv(['john', 'doe', 'john.doe@example.com'], $delimiter, $enclosure, $escape);
    }

    public function fputcsvProvider()
    {
        return [
            'wrong delimiter' => ['toto', '"', '\\'],
            'wrong enclosure' => [',', 'é', '\\'],
            'wrong escape' => [',', '"', 'à'],
        ];
    }
}
