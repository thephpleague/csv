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

use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

use function fclose;
use function fgetcsv;
use function fwrite;
use function rewind;
use function str_replace;
use function tmpfile;

final class CallbackStreamFilterTest extends TestCase
{
    #[Test]
    public function it_can_swap_the_delimiter_on_read(): void
    {
        $document = <<<CSV
observedOn💩temperature💩place
2023-10-01💩18💩Yamoussokro
2023-10-02💩21💩Yamoussokro
2023-10-03💩15💩Yamoussokro
2023-10-01💩22💩Abidjan
2023-10-02💩19💩Abidjan
2023-10-03💩💩Abidjan
CSV;

        $reader = Reader::fromString($document);
        $reader->setDelimiter("\x02");
        CallbackStreamFilter::register('swap.delemiter.in', fn (string $bucket): string => str_replace('💩', "\x02", $bucket));
        StreamFilter::appendOnReadTo($reader, 'swap.delemiter.in');
        $reader->setHeaderOffset(0);

        self::assertSame(
            ['observedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro'],
            $reader->first()
        );
    }

    #[Test]
    public function it_can_swap_the_delimiter_on_write(): void
    {
        $writer = Writer::fromString();
        $writer->setDelimiter("\x02");
        CallbackStreamFilter::register('swap.delemiter.out', fn (string $bucket): string => str_replace("\x02", '💩', $bucket));
        StreamFilter::prependOnWriteTo($writer, 'swap.delemiter.out');

        $writer->insertOne(['observeedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro']);
        self::assertSame('2023-10-01💩18💩Yamoussokro'."\n", $writer->toString());
        self:;
        self::assertContains('swap.delemiter.out', CallbackStreamFilter::registeredFilternames());
    }


    #[Test]
    public function it_can_add_stream_callbacks_as_stream_filters(): void
    {
        CallbackStreamFilter::register('string.to.upper', 'strtoupper');
        self::assertTrue(CallbackStreamFilter::isRegistered('string.to.upper'));
        self::assertFalse(CallbackStreamFilter::isRegistered('string.to.lower'));
    }

    #[Test]
    public function it_can_not_add_twice_the_same_callback_with_the_same_name(): void
    {
        CallbackStreamFilter::register('string.to.lower', strtolower(...));

        $this->expectExceptionObject(new LogicException('The stream filter "string.to.lower" is already registered.'));
        CallbackStreamFilter::register('string.to.lower', strtolower(...));
    }

    #[Test]
    public function it_can_be_added_to_a_csv_document(): void
    {
        $csv = "title1,title2,title3\rcontent11,content12,content13\rcontent21,content22,content23\r";
        $document = Reader::fromString($csv);
        $document->setHeaderOffset(0);

        CallbackStreamFilter::register('swap.carrier.return', fn (string $bucket): string => str_replace("\r", "\n", $bucket));
        StreamFilter::appendOnReadTo($document, 'swap.carrier.return');
        self::assertSame([
            'title1' => 'content11',
            'title2' => 'content12',
            'title3' => 'content13',
        ], $document->first());
    }

    #[Test]
    public function it_can_be_added_to_a_stream(): void
    {
        $csv = "title1,title2,title3\rcontent11,content12,content13\rcontent21,content22,content23\r";

        $stream = tmpfile();
        fwrite($stream, $csv);
        rewind($stream);
        CallbackStreamFilter::register('toUpper', 'strtoupper');
        StreamFilter::appendOnReadTo($stream, 'swap.carrier.return');
        StreamFilter::prependOnReadTo($stream, 'toUpper');
        $data = [];
        while (($record = fgetcsv($stream, 1000, ',', escape: '\\')) !== false) {
            $data[] = $record;
        }
        fclose($stream);

        self::assertSame(['TITLE1', 'TITLE2', 'TITLE3'], $data[0]);
    }

    #[Test]
    #[DataProvider('provideInvalidInternalFunctions')]
    public function it_can_not_register_an_internal_function(callable $function): void
    {
        $this->expectException(ValueError::class);

        CallbackStreamFilter::register('filtername', $function);
    }

    /**
     * @return iterable<string, array{function:callable}>
     */
    public static function provideInvalidInternalFunctions(): iterable
    {
        yield 'internal function with more than 1 parameter given as a string' => ['function' => 'explode'];
        yield 'internal function with more than 1 parameter given as a closure' => ['function' => explode(...)];
        yield 'internal function with no parameter given as a string' => ['function' => 'time'];
        yield 'internal function with no parameter given as a closure' => ['function' => time(...)];
    }

    #[Test]
    public function it_will_fail_to_return_the_callback_if_it_is_not_registered(): void
    {
        $this->expectException(OutOfBoundsException::class);

        CallbackStreamFilter::callback('foo,bar.baz');
    }
}
