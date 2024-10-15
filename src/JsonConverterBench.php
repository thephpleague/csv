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

use PhpBench\Attributes as Bench;

use function assert;
use function fseek;
use function ftell;
use function fwrite;
use function json_encode;
use function tmpfile;

use const SEEK_END;

final class JsonConverterBench
{
    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) <= 8000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonEncode(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        /** @var int $bytes */
        $bytes = fwrite($tmpFile, json_encode($document)); /* @phpstan-ignore-line */

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingDefaultJsonConverter(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = JsonConverter::create()->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithForceObject(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = JsonConverter::create()->withForceObject()->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithPrettyPrint(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = JsonConverter::create()->withPrettyPrint()->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithSmallChunkSize(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = JsonConverter::create()->chunkSize(1)->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    private function getDocument(): Reader
    {
        $document = Reader::createFromPath(dirname(__DIR__).'/test_files/prenoms.csv');
        $document->setHeaderOffset(0);
        $document->setDelimiter(';');
        CharsetConverter::addTo($document, 'iso-8859-15', 'utf-8');

        return $document;
    }

    /**
     * @param resource $stream
     */
    private function assertSameSize(int $bytes, $stream): void
    {
        fseek($stream, 0, SEEK_END);
        assert($bytes === ftell($stream));
    }
}
