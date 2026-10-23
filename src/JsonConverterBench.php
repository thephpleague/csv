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
use function fclose;
use function fseek;
use function ftell;
use function fwrite;
use function json_encode;
use function str_repeat;
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

        $bytes = (new JsonConverter())->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithForceObject(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = (new JsonConverter())->withForceObject()->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithPrettyPrint(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = (new JsonConverter())->withPrettyPrint()->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    #[Bench\OutputTimeUnit('seconds')]
    #[Bench\Assert('mode(variant.mem.peak) < 4000000'), Bench\Assert('mode(variant.time.avg) < 10000000')]
    public function benchUsingJsonConverterWithSmallChunkSize(): void
    {
        $document = $this->getDocument();
        $tmpFile = tmpfile();

        $bytes = (new JsonConverter())->chunkSize(1)->save($document, $tmpFile);

        $this->assertSameSize($bytes, $tmpFile);
    }

    /** @param array{kind: string, rows: positive-int, chunkSize: positive-int, indentSize: positive-int, flags: int, pretty: bool} $params */
    #[Bench\OutputTimeUnit('milliseconds')]
    #[Bench\Iterations(7)]
    #[Bench\Revs(1)]
    #[Bench\ParamProviders('providePrettyPrintScenarios')]
    public function benchStreamingPrettyPrint(array $params): void
    {
        $row = match ($params['kind']) {
            'short' => ['name' => 'hello "world" \\ unicode Ж', 'nested' => ['a' => [1, null, true]]],
            'escaped' => ['text' => str_repeat('"\\{}[],:', 40)],
            'numbers' => ['values' => [1, -2, 3.5, true, false, null]],
            'long' => ['text' => str_repeat('Ordinary text with unicode Ж and spaces. ', 200)],
            default => ['text' => str_repeat('Ordinary text with unicode Ж and spaces. ', 8)],
        };
        $records = (static function () use ($row, $params) {
            for ($i = 0; $i < $params['rows']; ++$i) {
                yield ['id' => $i] + $row;
            }
        })();
        $converter = (new JsonConverter($params['flags']))->chunkSize($params['chunkSize']);
        if ($params['pretty']) {
            $converter = $converter->withPrettyPrint($params['indentSize']);
        }

        $stream = tmpfile();
        $bytes = $converter->save($records, $stream);
        $this->assertSameSize($bytes, $stream);
        fclose($stream);
    }

    /** @return iterable<string, array{kind: string, rows: positive-int, chunkSize: positive-int, indentSize: positive-int, flags: int, pretty: bool}> */
    public function providePrettyPrintScenarios(): iterable
    {
        $defaults = ['kind' => 'text', 'rows' => 20_000, 'chunkSize' => 500, 'indentSize' => 4, 'flags' => 0, 'pretty' => true];

        yield 'short-fields' => ['kind' => 'short'] + $defaults;
        yield 'text-320' => $defaults;
        yield 'text-8000' => ['kind' => 'long', 'rows' => 1_000] + $defaults;
        yield 'escape-heavy' => ['kind' => 'escaped'] + $defaults;
        yield 'numbers' => ['kind' => 'numbers'] + $defaults;
        yield 'chunk-size-1' => ['chunkSize' => 1] + $defaults;
        yield 'indent-size-2' => ['indentSize' => 2] + $defaults;
        yield 'force-object' => ['flags' => JSON_FORCE_OBJECT] + $defaults;
        yield 'compact-control' => ['pretty' => false] + $defaults;
    }

    private function getDocument(): Reader
    {
        $document = Reader::from(dirname(__DIR__).'/test_files/prenoms.csv');
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
