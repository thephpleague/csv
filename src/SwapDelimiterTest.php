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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SwapDelimiterTest extends TestCase
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

        $reader = Reader::createFromString($document);
        $reader->setDelimiter("\x02");
        SwapDelimiter::addTo($reader, '💩');
        $reader->setHeaderOffset(0);

        self::assertSame(
            ['observedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro'],
            $reader->first()
        );
    }

    #[Test]
    public function it_can_swap_the_delimiter_on_write(): void
    {
        $writer = Writer::createFromString();
        $writer->setDelimiter("\x02");
        SwapDelimiter::addTo($writer, '💩');

        $writer->insertOne(['observeedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro']);
        self::assertSame('2023-10-01💩18💩Yamoussokro'."\n", $writer->toString());
    }
}
