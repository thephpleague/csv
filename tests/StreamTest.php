<?php

/**
 * League.Csv (https://csv.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/csv/blob/master/LICENSE (MIT License)
 * @version 9.2.0
 * @link    https://github.com/thephpleague/csv
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LeagueTest\Csv;

use League\Csv\Exception;
use League\Csv\Stream;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use TypeError;
use const STREAM_FILTER_READ;
use function curl_init;
use function fopen;
use function fputcsv;
use function stream_context_create;
use function stream_wrapper_register;
use function stream_wrapper_unregister;

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
        self::expectException(Exception::class);
        $toto = clone new Stream(fopen('php://temp', 'r+'));
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithInvalidParameter()
    {
        self::expectException(TypeError::class);
        new Stream(__DIR__.'/data/foo.csv');
    }

    /**
     * @covers ::__construct
     */
    public function testCreateStreamWithWrongResourceType()
    {
        self::expectException(TypeError::class);
        new Stream(curl_init());
    }

    /**
     * @covers ::createFromPath
     */
    public function testCreateStreamFromPath()
    {
        $path = 'no/such/file.csv';
        self::expectException(Exception::class);
        self::expectExceptionMessage('`'.$path.'`: failed to open stream: No such file or directory');
        Stream::createFromPath($path);
    }

    /**
     * @covers ::createFromPath
     * @covers ::current
     * @covers ::getCurrentRecord
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
        $stream->setFlags(SplFileObject::READ_AHEAD | SplFileObject::READ_CSV);
        $stream->rewind();
        self::assertInternalType('array', $stream->current());
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
        self::expectException(Exception::class);
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
        self::assertInternalType('array', $stream->__debugInfo());
    }

    /**
     * @covers ::seek
     */
    public function testSeekThrowsException()
    {
        self::expectException(Exception::class);
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
        self::assertSame(['Aaron', '55', 'M', '2004'], $doc->current());
    }

    /**
     * @covers ::rewind
     */
    public function testRewindThrowsException()
    {
        self::expectException(Exception::class);
        $stream = new Stream(fopen('php://stdin', 'r'));
        $stream->rewind();
    }

    /**
     * @covers ::seek
     */
    public function testCreateStreamWithNonSeekableStream()
    {
        self::expectException(Exception::class);
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
        self::assertSame([',', '"', '\\'], $doc->getCsvControl());
        $expected = [';', '|', '"'];
        $doc->setCsvControl(...$expected);
        self::assertSame($expected, $doc->getCsvControl());
        self::expectException(Exception::class);
        $doc->setCsvControl(...['foo']);
    }

    /**
     * @covers ::appendFilter
     */
    public function testAppendStreamFilterThrowsException()
    {
        $filtername = 'foo.bar';
        self::expectException(Exception::class);
        self::expectExceptionMessage('unable to locate filter `'.$filtername.'`');
        $stream = Stream::createFromPath('php://temp', 'r+');
        $stream->appendFilter($filtername, STREAM_FILTER_READ);
    }
}
