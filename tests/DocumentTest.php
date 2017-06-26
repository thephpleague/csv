<?php

namespace LeagueTest\Csv;

use League\Csv\Document;
use League\Csv\Exception\LengthException;
use League\Csv\Exception\OutOfRangeException;
use League\Csv\Exception\RuntimeException;
use LogicException;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;

/**
 * @group csv
 * @coversDefaultClass League\Csv\Document
 */
class DocumentTest extends TestCase
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
        $toto = clone new Document(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter()
    {
        $this->expectException(TypeError::class);
        new Document(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        $this->expectException(RuntimeException::class);
        new Document(fopen('php://stdin', 'r'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType()
    {
        $this->expectException(TypeError::class);
        new Document(curl_init());
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

        $stream = Document::createFromPath(
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
     * @dataProvider fputcsvProvider
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function testfputcsv($delimiter, $enclosure, $escape)
    {
        $this->expectException(LengthException::class);
        $stream = new Document(fopen('php://temp', 'r+'));
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
        $stream = new Document(fopen('php://temp', 'r+'));
        $this->assertInternalType('array', $stream->__debugInfo());
    }

    /**
     * @covers ::seek
     */
    public function testSeekThrowsException()
    {
        $this->expectException(OutOfRangeException::class);
        $stream = new Document(fopen('php://temp', 'r+'));
        $stream->seek(-1);
    }

    /**
     * @covers ::seek
     */
    public function testSeek()
    {
        $doc = Document::createFromPath(__DIR__.'/data/prenoms.csv');
        $doc->setCsvControl(';');
        $doc->setFlags(SplFileObject::READ_CSV);
        $doc->seek(1);
        $this->assertSame(['Aaron', '55', 'M', '2004'], $doc->current());
    }
}
