<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Stream;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;
use function curl_init;
use function fopen;
use function fputcsv;
use function stream_context_create;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use const PHP_VERSION_ID;
use const STREAM_FILTER_READ;

/**
 * @group csv
 * @coversDefaultClass \League\Csv\Stream
 */
class StreamTest extends TestCase
{
    public function setUp(): void
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);
    }

    public function tearDown(): void
    {
        stream_wrapper_unregister(StreamWrapper::PROTOCOL);
    }

    /**
     * @covers ::__clone
     */
    public function testCloningIsForbidden(): void
    {
        $this->expectException(Exception::class);
        $toto = clone new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter(): void
    {
        $this->expectException(TypeError::class);
        new Stream(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType(): void
    {
        $this->expectException(TypeError::class);
        new Stream(curl_init());
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateStreamFromPath(): void
    {
        $path = 'no/such/file.csv';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('`'.$path.'`: failed to open stream: No such file or directory');
        Stream::createFromPath($path);
    }

    /**
     * @covers ::createFromPath
     * @covers ::current
     * @covers ::getCurrentRecord
     */
    public function testCreateStreamFromPathWithContext(): void
    {
        /** @var resource $fp */
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
        $stream->setFlags(SplFileObject::READ_AHEAD | SplFileObject::READ_CSV);
        $stream->rewind();
        self::assertIsArray($stream->current());
    }

    /**
     * @covers ::fputcsv
     * @covers ::filterControl
     *
     * @dataProvider fputcsvProvider
     */
    public function testfputcsv(string $delimiter, string $enclosure, string $escape): void
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->fputcsv(['john', 'doe', 'john.doe@example.com'], $delimiter, $enclosure, $escape);
    }

    public function fputcsvProvider(): array
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
    public function testVarDump(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        self::assertIsArray($stream->__debugInfo());
    }

    /**
     * @covers ::seek
     */
    public function testSeekThrowsException(): void
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->seek(-1);
    }

    /**
     * @covers ::seek
     */
    public function testSeek(): void
    {
        $doc = Stream::createFromPath(__DIR__.'/data/prenoms.csv');
        $doc->setCsvControl(';');
        $doc->setFlags(SplFileObject::READ_CSV);
        $doc->seek(1);
        self::assertSame(['Aaron', '55', 'M', '2004'], $doc->current());
    }

    /**
     * @covers ::seek
     * @covers ::key
     */
    public function testSeekToPositionZero(): void
    {
        $doc = Stream::createFromString();
        $doc->seek(0);
        self::assertSame(0, $doc->key());
    }

    /**
     * @covers ::rewind
     */
    public function testRewindThrowsException(): void
    {
        $this->expectException(Exception::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->rewind();
    }

    /**
     * @covers ::seek
     */
    public function testCreateStreamWithNonSeekableStream(): void
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
    public function testCsvControl(): void
    {
        $doc = Stream::createFromString('foo,bar');
        self::assertSame([',', '"', '\\'], $doc->getCsvControl());
        $expected = [';', '|', '"'];
        $doc->setCsvControl(...$expected);
        self::assertSame($expected, $doc->getCsvControl());
        $this->expectException(Exception::class);
        $doc->setCsvControl(...['foo']);
    }

    public function testCsvControlThrowsOnEmptyEscapeString(): void
    {
        if (70400 <= PHP_VERSION_ID) {
            $this->markTestSkipped('This test is only for PHP7.4- versions');
        }
        $this->expectException(Exception::class);
        $doc = Stream::createFromString();
        $doc->setCsvControl(...[';', '|', '']);
    }

    public function testCsvControlAcceptsEmptyEscapeString(): void
    {
        if (70400 > PHP_VERSION_ID) {
            $this->markTestSkipped('This test is only for PHP7.4+ versions');
        }
        $doc = Stream::createFromString();
        $expected = [';', '|', ''];
        $doc->setCsvControl(...$expected);
        self::assertSame($expected, $doc->getCsvControl());
    }

    /**
     * @covers ::appendFilter
     */
    public function testAppendStreamFilterThrowsException(): void
    {
        $filtername = 'foo.bar';
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('unable to locate filter `'.$filtername.'`');
        $stream = Stream::createFromPath('php://temp', 'r+');
        $stream->appendFilter($filtername, STREAM_FILTER_READ);
    }
}
