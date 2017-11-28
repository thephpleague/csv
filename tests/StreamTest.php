<?php

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\Stream;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;

/**
 * @group csv
 * @coversDefaultClass League\Csv\Stream
 */
class StreamTest extends TestCase
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
        $this->expectException(Exception::class);
        $toto = clone new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter()
    {
        $this->expectException(TypeError::class);
        new Stream(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType()
    {
        $this->expectException(TypeError::class);
        new Stream(curl_init());
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateStreamFromPath()
    {
        $path = 'no/such/file.csv';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('`'.$path.'`: failed to open stream: No such file or directory');
        Stream::createFromPath($path);
    }

    /**
     * @covers ::createFromPath
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

        $stream = Stream::createFromPath(
            StreamWrapper::PROTOCOL.'://stream',
            'r+',
            stream_context_create([StreamWrapper::PROTOCOL => ['stream' => $fp]])
        );
        $stream->setFlags(SplFileObject::READ_AHEAD);
        $stream->rewind();
        $this->assertInternalType('array', $stream->current());
    }

    /**
     * @covers ::fputcsv
     * @covers ::filterControl
     *
     * @dataProvider fputcsvProvider
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function testfputcsv($delimiter, $enclosure, $escape)
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://temp', 'r+'));
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

    /**
     * @covers ::__debugInfo
     */
    public function testVarDump()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $this->assertInternalType('array', $stream->__debugInfo());
    }

    /**
     * @covers ::seek
     */
    public function testSeekThrowsException()
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->seek(-1);
    }

    /**
     * @covers ::seek
     */
    public function testSeek()
    {
        $doc = Stream::createFromPath(__DIR__.'/data/prenoms.csv');
        $doc->setCsvControl(';');
        $doc->setFlags(SplFileObject::READ_CSV);
        $doc->seek(1);
        $this->assertSame(['Aaron', '55', 'M', '2004'], $doc->current());
    }

    /**
     * @covers ::rewind
     */
    public function testRewindThrowsException()
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->rewind();
    }

    /**
     * @covers ::seek
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->seek(3);
    }

    /**
     * @covers ::setCsvControl
     * @covers ::getCsvControl
     * @covers ::filterControl
     */
    public function testCsvControl()
    {
        $doc = Stream::createFromString('foo,bar');
        $this->assertSame([',', '"', '\\'], $doc->getCsvControl());
        $expected = [';', '|', '"'];
        $doc->setCsvControl(...$expected);
        $this->assertSame($expected, $doc->getCsvControl());
        $this->expectException(Exception::class);
        $doc->setCsvControl(...['foo']);
    }

    /**
     * @covers ::appendFilter
     */
    public function testAppendStreamFilterThrowsException()
    {
        $filtername = 'foo.bar';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unable to locate filter `'.$filtername.'`');
        $stream = Stream::createFromPath('php://temp', 'r+');
        $stream->appendFilter($filtername, STREAM_FILTER_READ);
    }
}
