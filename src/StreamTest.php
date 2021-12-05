<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Csv;

use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;
use function curl_init;
use function feof;
use function fopen;
use function fputcsv;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function in_array;
use function stream_context_create;
use function stream_context_get_options;
use function stream_get_wrappers;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use const PHP_MAJOR_VERSION;
use const STREAM_FILTER_READ;

/**
 * @group csv
 * @coversDefaultClass \League\Csv\Stream
 */
final class StreamTest extends TestCase
{
    protected function setUp(): void
    {
        stream_wrapper_register(StreamWrapper::PROTOCOL, StreamWrapper::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister(StreamWrapper::PROTOCOL);
    }

    /**
     * @covers ::__clone
     * @covers \League\Csv\UnavailableStream::dueToForbiddenCloning
     */
    public function testCloningIsForbidden(): void
    {
        $this->expectException(UnavailableStream::class);

        clone new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter(): void
    {
        $this->expectException(TypeError::class);
        new Stream(__DIR__.'/../test_files/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType(): void
    {
        if (8 > PHP_MAJOR_VERSION) {
            self::markTestSkipped('This test is only for PHP7 versions');
        }

        $this->expectException(TypeError::class);
        new Stream(curl_init());
    }

    /**
     * @covers ::createFromPath
     * @covers \League\Csv\UnavailableStream
     */
    public function testCreateStreamFromPath(): void
    {
        $path = 'no/such/file.csv';
        $this->expectException(UnavailableStream::class);
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
     * @covers \League\Csv\InvalidArgument::dueToInvalidEscapeCharacter
     * @covers \League\Csv\InvalidArgument::dueToInvalidEnclosureCharacter
     * @covers \League\Csv\InvalidArgument::dueToInvalidDelimiterCharacter
     *
     * @dataProvider fputcsvProvider
     */
    public function testfputcsv(string $delimiter, string $enclosure, string $escape): void
    {
        $this->expectException(InvalidArgument::class);
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
        $debugInfo = $stream->__debugInfo();

        self::assertArrayHasKey('delimiter', $debugInfo);
        self::assertArrayHasKey('enclosure', $debugInfo);
        self::assertArrayHasKey('escape', $debugInfo);
        self::assertArrayHasKey('stream_filters', $debugInfo);
    }

    /**
     * @covers ::seek
     * @covers \League\Csv\InvalidArgument::dueToInvalidSeekingPosition
     */
    public function testSeekThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->seek(-1);
    }

    public function testFSeekThrowsExceptionOnNonSeakableResource(): void
    {
        $this->expectException(UnavailableFeature::class);
        $stream = new Stream(fopen('php://output', 'w'));
        $stream->fputcsv(['foo', 'bar']);
        $stream->fseek(-1);
    }

    /**
     * @covers ::seek
     */
    public function testSeek(): void
    {
        $doc = Stream::createFromPath(__DIR__.'/../test_files/prenoms.csv');
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
     * @covers \League\Csv\UnavailableFeature::dueToMissingStreamSeekability
     */
    public function testRewindThrowsException(): void
    {
        $this->expectException(UnavailableFeature::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->rewind();
    }

    /**
     * @covers ::seek
     * @covers \League\Csv\UnavailableFeature::dueToMissingStreamSeekability
     */
    public function testCreateStreamWithNonSeekableStream(): void
    {
        $this->expectException(UnavailableFeature::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->seek(3);
    }

    /**
     * @covers ::setCsvControl
     * @covers ::getCsvControl
     * @covers ::filterControl
     * @covers \League\Csv\InvalidArgument::dueToInvalidDelimiterCharacter
     */
    public function testCsvControl(): void
    {
        $doc = Stream::createFromString('foo,bar');
        self::assertSame([',', '"', '\\'], $doc->getCsvControl());
        $expected = [';', '|', '"'];
        $doc->setCsvControl(...$expected);
        self::assertSame($expected, $doc->getCsvControl());
        $this->expectException(InvalidArgument::class);
        $doc->setCsvControl(...['foo']);
    }

    public function testCsvControlAcceptsEmptyEscapeString(): void
    {
        $doc = Stream::createFromString();
        $expected = [';', '|', ''];
        $doc->setCsvControl(...$expected);
        self::assertSame($expected, $doc->getCsvControl());
    }

    /**
     * @covers \League\Csv\InvalidArgument::dueToStreamFilterNotFound
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

final class StreamWrapper
{
    const PROTOCOL = 'leaguetest';

    /**
     * @var resource
     */
    public $context;

    /**
     * @var resource
     */
    private $stream;

    public static function register(): void
    {
        if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::PROTOCOL, __CLASS__);
        }
    }

    public function stream_open(string $path, string $mode, int $options, string &$opened_path = null): bool
    {
        $options = stream_context_get_options($this->context);
        if (!isset($options[self::PROTOCOL]['stream'])) {
            return false;
        }

        $this->stream = $options[self::PROTOCOL]['stream'];

        return true;
    }

    /**
     * @param int<0, max> $count
     *
     * @return string|false
     */
    public function stream_read(int $count)
    {
        return fread($this->stream, $count);
    }

    /**
     * {@inheritdoc}
     *
     * @return int|false
     */
    public function stream_write(string $data)
    {
        return fwrite($this->stream, $data);
    }

    /**
     * {@inheritdoc}
     *
     * @return int|false
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        fseek($this->stream, $whence);

        return true;
    }

    public function stream_stat(): array
    {
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 33206,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
    }
}
