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

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

use function implode;
use function xdebug_get_headers;

use const JSON_FORCE_OBJECT;
use const JSON_HEX_QUOT;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

#[Group('converter')]
final class JsonConverterTest extends TestCase
{
    #[Test]
    public function it_will_convert_a_tabular_data_reader_into_a_json(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');
        $converter = (new JsonConverter())
            ->chunkSize(2)
            ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
            ->removeFlags(JSON_FORCE_OBJECT)
            ->depth(24);

        $records = (new Statement())->offset(3)->limit(5)->process($csv);

        $tmp = tmpfile();
        $converter->save($records, $tmp);
        rewind($tmp);

        $nativeJson = json_encode($records, $converter->flags, $converter->depth);

        self::assertSame(stream_get_contents($tmp), $nativeJson);
        self::assertSame($converter->encode($records), $nativeJson);
        self::assertSame(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT, $converter->flags);
        self::assertSame(24, $converter->depth);
        self::assertSame(4, $converter->indentSize);
    }

    #[Test]
    public function it_has_default_values(): void
    {
        $converter = (new JsonConverter());

        self::assertSame(
            $converter,
            $converter
                ->addFlags(0)
                ->removeFlags(0)
                ->depth(512)
                ->chunkSize(500)
                ->format(JsonFormat::Standard)
        );
    }

    #[Test]
    public function it_fails_if_the_depth_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new JsonConverter())->depth(-1); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_if_the_indentation_size_is_invalud(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new JsonConverter())->withPrettyPrint(0); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_if_the_chunk_size_is_invalud(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new JsonConverter())->chunkSize(0); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_returns_a_null_object_if_the_collection_is_empty(): void
    {
        $converter = (new JsonConverter());

        self::assertSame('[]', $converter->encode([]));
        self::assertSame('{}', $converter->addFlags(JSON_FORCE_OBJECT)->encode([]));
    }

    #[Test]
    public function it_can_manipulate_the_record_prior_to_json_encode(): void
    {
        $converter = (new JsonConverter())
            ->formatter(fn (array $value, int|string $offset): array => array_map(strtoupper(...), $value));

        self::assertSame('[{"foo":"BAR"}]', $converter->encode([['foo' => 'bar']]));
    }

    #[Test]
    public function it_can_use_syntactic_sugar_methods_to_set_json_flags(): void
    {
        $usingJsonFlags = (new JsonConverter())
            ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
            ->removeFlags(JSON_HEX_QUOT)
            ->depth(24);

        $usingMethodFlags = (new JsonConverter())
            ->withPrettyPrint()
            ->withUnescapedSlashes()
            ->withForceObject()
            ->withoutHexQuot()
            ->depth(24);

        self::assertSame($usingJsonFlags->encode([['foo' => 'bar']]), $usingMethodFlags->encode([['foo' => 'bar']]));
    }

    #[Test]
    public function it_can_make_the_generated_json_downloadable_ont_the_fly(): void
    {
        if (!function_exists('xdebug_get_headers')) {
            self::markTestSkipped(__METHOD__.' needs the xdebug extension to run');
        }

        ob_start();
        (new JsonConverter())->download([['foo' => 'bar']], 'foobar.json');
        $output = ob_get_clean();
        $headers = xdebug_get_headers();
        if ([] === $headers) {
            self::markTestSkipped(__METHOD__.' needs the xdebug function `xdebug_get_headers` to run and returns actual data.');
        }
        $header = implode("\n", $headers);

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        self::assertStringContainsString('content-type: application/json', strtolower($header));
        self::assertStringContainsString('content-transfer-encoding: binary', strtolower($header));
        self::assertStringContainsString('content-description: File Transfer', $header);
        self::assertStringContainsString('content-disposition: attachment;filename="foobar.json"', $header);
        self::assertSame('[{"foo":"bar"}]', $output);
    }

    #[Test]
    public function it_fails_if_the_destination_path_type_is_invalid(): void
    {
        $this->expectException(TypeError::class);

        (new JsonConverter())->save([['foo' => 'bar']], new DateTimeImmutable()); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_to_write_to_the_destination_path_if_it_is_open_in_read_mode_only(): void
    {
        $this->expectExceptionObject(new RuntimeException('Unable to write `[` to the destination path `'.__FILE__.'`.'));

        /** @var resource $stream */
        $stream = fopen(__FILE__, 'r');

        (new JsonConverter())->save([['foo' => 'bar']], $stream);
    }

    #[Test]
    public function it_can_set_the_indentation_size_using_pretty_print(): void
    {
        $converter = (new JsonConverter());
        self::assertSame(4, $converter->indentSize);
        self::assertFalse($converter->usePrettyPrint());

        $converter = $converter->withPrettyPrint();
        self::assertSame(4, $converter->indentSize);
        self::assertTrue($converter->usePrettyPrint());

        $converter = $converter->withPrettyPrint(2);
        self::assertSame(2, $converter->indentSize);
        self::assertTrue($converter->usePrettyPrint());

        $converter = $converter->withPrettyPrint();
        self::assertSame(2, $converter->indentSize);
        self::assertTrue($converter->usePrettyPrint());

        $converter = $converter->withoutPrettyPrint();
        self::assertSame(2, $converter->indentSize);
        self::assertFalse($converter->usePrettyPrint());
    }

    #[Test]
    public function it_can_generate_an_empty_ldjson_file(): void
    {
        $converter = (new JsonConverter())->format(JsonFormat::NdJson);

        self::assertSame('', $converter->encode([]));
    }

    #[Test]
    public function it_can_generate_ldjson_file_with_data(): void
    {
        $converter = (new JsonConverter())->format(JsonFormat::NdJson);

        self::assertSame(
            '{"foo":"bar"}'."\n".'{"foo":"bar"}'."\n".'{"foo":"bar"}'."\n",
            $converter->encode([
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                ['foo' => 'bar'],
            ])
        );
    }

    #[Test]
    public function it_can_tell_which_fornat_it_is_using(): void
    {
        $converter = new JsonConverter();
        $newConverter = $converter->format(JsonFormat::NdJson);

        self::assertSame(JsonFormat::Standard, $converter->format);
        self::assertSame(JsonFormat::NdJson, $newConverter->format);
    }

    #[Test]
    public function it_can_force_generate_ldjson_with_list_headers(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv')
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');

        $data = (new JsonConverter())
            ->format(JsonFormat::NdJsonHeader)
            ->encode(
                records: $csv->slice(0, 3),
                header: $csv->getHeader(),
            );

        self::assertStringContainsString('["prenoms","nombre","sexe","annee"]', $data);
    }

    #[Test]
    public function it_can_force_generate_ldjson_without_headers(): void
    {
        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv')
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');

        $data = (new JsonConverter())
            ->format(JsonFormat::NdJsonHeaderLess)
            ->encode(
                records: $csv->slice(0, 3),
                header: $csv->getHeader(),
            );

        self::assertStringNotContainsString('["prenoms","nombre","sexe","annee"]', $data);
    }

    #[Test]
    public function it_fails_generating_the_ldjson_if_no_header_is_provided(): void
    {
        $this->expectException(InvalidArgument::class);

        $csv = Reader::from(__DIR__.'/../test_files/prenoms.csv')
            ->setDelimiter(';')
            ->setHeaderOffset(0);

        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');

        $this->expectException(InvalidArgument::class);

        (new JsonConverter())
            ->format(JsonFormat::NdJsonHeader)
            ->encode(records: $csv->slice(0, 3));
    }
}
