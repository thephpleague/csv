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
        $csv = Reader::createFromPath(__DIR__.'/../test_files/prenoms.csv');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        CharsetConverter::addTo($csv, 'iso-8859-15', 'utf-8');
        $converter = JsonConverter::create()
            ->chunkSize(2)
            ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
            ->removeFlags(JSON_FORCE_OBJECT)
            ->depth(24);

        $records = Statement::create()->offset(3)->limit(5)->process($csv);

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
        $converter = JsonConverter::create();

        self::assertSame(
            $converter,
            $converter
                ->indentSize(4)
                ->addFlags(0)
                ->removeFlags(0)
                ->depth(512)
                ->chunkSize(500)
        );
    }

    #[Test]
    public function it_fails_if_the_depth_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonConverter::create()->depth(-1); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_if_the_indentation_size_is_invalud(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonConverter::create()->indentSize(0); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_if_the_chunk_size_is_invalud(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonConverter::create()->chunkSize(0); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_only_uses_indentation_if_pretty_print_is_present(): void
    {
        self::assertSame(
            json_encode([['foo' => 'bar']]),
            JsonConverter::create()->indentSize(23)->encode([['foo' => 'bar']]),
        );
    }

    #[Test]
    public function it_returns_a_null_object_if_the_collection_is_empty(): void
    {
        $converter = JsonConverter::create();

        self::assertSame('[]', $converter->encode([]));
        self::assertSame('{}', $converter->addFlags(JSON_FORCE_OBJECT)->encode([]));
    }

    #[Test]
    public function it_can_manipulate_the_record_prior_to_json_encode(): void
    {
        $converter = JsonConverter::create()
            ->formatter(fn (array $value, int|string $offset): array => array_map(strtoupper(...), $value));

        self::assertSame('[{"foo":"BAR"}]', $converter->encode([['foo' => 'bar']]));
    }

    #[Test]
    public function it_can_use_syntactic_sugar_methods_to_set_json_flags(): void
    {
        $usingJsonFlags = JsonConverter::create()
            ->addFlags(JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, JSON_FORCE_OBJECT)
            ->removeFlags(JSON_HEX_QUOT)
            ->depth(24);

        $usingMethodFlags = JsonConverter::create()
            ->withPrettyPrint()
            ->withUnescapedSlashes()
            ->withForceObject()
            ->withoutHexQuot()
            ->depth(24);

        self::assertEquals($usingJsonFlags, $usingMethodFlags);
    }

    #[Test]
    public function it_can_make_the_generated_json_downloadable_ont_the_fly(): void
    {
        if (!function_exists('xdebug_get_headers')) {
            self::markTestSkipped(__METHOD__.' needs the xdebug extension to run');
        }

        ob_start();
        JsonConverter::create()->download([['foo' => 'bar']], 'foobar.json');
        $output = ob_get_clean();
        $headers = xdebug_get_headers();

        // Due to the variety of ways the xdebug expresses Content-Type of text files,
        // we cannot count on complete string matching.
        self::assertStringContainsString('content-type: application/json', strtolower($headers[0]));
        self::assertSame('content-transfer-encoding: binary', strtolower($headers[1]));
        self::assertSame('content-description: File Transfer', $headers[2]);
        self::assertStringContainsString('content-disposition: attachment; filename="foobar.json"', $headers[3]);
        self::assertSame('[{"foo":"bar"}]', $output);
    }

    #[Test]
    public function it_fails_if_the_destination_path_type_is_invalid(): void
    {
        $this->expectException(TypeError::class);

        JsonConverter::create()->save([['foo' => 'bar']], new DateTimeImmutable()); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_fails_to_write_to_the_destination_path_if_it_is_open_in_read_mode_only(): void
    {
        $this->expectExceptionObject(new RuntimeException('Unable to write `[` to the destination path `'.__FILE__.'`.'));

        /** @var resource $stream */
        $stream = fopen(__FILE__, 'r');

        JsonConverter::create()->save([['foo' => 'bar']], $stream);
    }
}
