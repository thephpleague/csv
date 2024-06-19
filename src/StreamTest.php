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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;

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

use const STREAM_FILTER_READ;

#[Group('csv')]
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

    public function testCloningIsForbidden(): void
    {
        $this->expectException(UnavailableStream::class);

        clone Stream::createFromResource(STDOUT);
    }

    public function testCreateStreamWithInvalidParameter(): void
    {
        $this->expectException(TypeError::class);

        Stream::createFromResource(__DIR__.'/../test_files/foo.csv');
    }

    public function testCreateStreamWithWrongResourceType(): void
    {
        $this->expectException(TypeError::class);

        /** @var resource $resource */
        $resource = stream_filter_append(STDOUT, 'string.rot13', STREAM_FILTER_WRITE);

        Stream::createFromResource($resource);
    }

    public function testCreateStreamFromPath(): void
    {
        $path = 'no/such/file.csv';
        $this->expectException(UnavailableStream::class);
        $this->expectExceptionMessage('`'.$path.'`: failed to open stream: No such file or directory');
        Stream::createFromPath($path);
    }

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

    #[DataProvider('fputcsvProvider')]
    public function testfputcsv(string $delimiter, string $enclosure, string $escape): void
    {
        $this->expectException(InvalidArgument::class);
        $stream = Stream::createFromResource(STDOUT);
        $stream->fputcsv(['john', 'doe', 'john.doe@example.com'], $delimiter, $enclosure, $escape);
    }

    public static function fputcsvProvider(): array
    {
        return [
            'wrong delimiter' => ['toto', '"', '\\'],
            'wrong enclosure' => [',', 'é', '\\'],
            'wrong escape' => [',', '"', 'à'],
        ];
    }

    public function testVarDump(): void
    {
        $stream = Stream::createFromResource(STDOUT);
        $debugInfo = $stream->__debugInfo();

        self::assertArrayHasKey('delimiter', $debugInfo);
        self::assertArrayHasKey('enclosure', $debugInfo);
        self::assertArrayHasKey('escape', $debugInfo);
        self::assertArrayHasKey('stream_filters', $debugInfo);
    }

    public function testSeekThrowsException(): void
    {
        $this->expectException(InvalidArgument::class);
        $stream = Stream::createFromResource(STDOUT);
        $stream->seek(-1);
    }

    public function testFSeekThrowsExceptionOnNonSeakableResource(): void
    {
        $this->expectException(UnavailableFeature::class);

        $stream = Stream::createFromResource(STDOUT);
        $stream->fputcsv(['foo', 'bar']);
        $stream->fseek(-1);
    }

    public function testSeek(): void
    {
        $doc = Stream::createFromPath(__DIR__.'/../test_files/prenoms.csv');
        $doc->setCsvControl(';');
        $doc->setFlags(SplFileObject::READ_CSV);
        $doc->seek(1);
        self::assertSame(['Aaron', '55', 'M', '2004'], $doc->current());
    }

    public function testSeekToPositionZero(): void
    {
        $doc = Stream::createFromString();
        $doc->seek(0);
        self::assertSame(0, $doc->key());
    }

    public function testRewindThrowsException(): void
    {
        $this->expectException(UnavailableFeature::class);

        $stream = Stream::createFromResource(STDIN);
        $stream->rewind();
    }

    public function testCreateStreamWithNonSeekableStream(): void
    {
        $this->expectException(UnavailableFeature::class);
        $stream = Stream::createFromResource(STDIN);
        $stream->seek(3);
    }

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

    public function testAppendStreamFilterThrowsException(): void
    {
        $filtername = 'foo.bar';
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('unable to locate filter `'.$filtername.'`');
        $stream = Stream::createFromPath('php://temp', 'r+');
        $stream->appendFilter($filtername, STREAM_FILTER_READ);
    }

    public function testIterateOverLines(): void
    {
        $text = <<<TEXT
Lorem ipsum dolor sit amet, consectetur adipiscing elit.
Duis nec sapien felis, ac sodales nisl.
Nulla vitae magna vitae purus aliquet consequat.
TEXT;
        $newText = '';
        $file = Stream::createFromString($text);
        $file->setMaxLineLen(20);
        foreach ($file as $line) {
            $newText .= $line."\n";
        }
        self::assertStringContainsString('Lorem ipsum dolor s', $newText);
        self::assertSame(20, $file->getMaxLineLen());
    }
}

final class StreamWrapper
{
    public const PROTOCOL = 'leaguetest';

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

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path = null): bool
    {
        $options = stream_context_get_options($this->context);
        if (!isset($options[self::PROTOCOL]['stream'])) {
            return false;
        }

        $this->stream = $options[self::PROTOCOL]['stream'];

        return true;
    }

    /**
     * @param int<1, max> $count
     */
    public function stream_read(int $count): string|false
    {
        return fread($this->stream, $count);
    }

    public function stream_write(string $data): int|false
    {
        return fwrite($this->stream, $data);
    }

    public function stream_tell(): int|false
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
