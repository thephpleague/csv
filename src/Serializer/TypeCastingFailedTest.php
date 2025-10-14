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

namespace League\Csv\Serializer;

use League\Csv\Reader;
use League\Csv\TabularDataReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function preg_quote;

final class TypeCastingFailedTest extends TestCase
{
    private TabularDataReader $reader;

    public function setUp(): void
    {
        $csv = <<<CSV
rating,title,author,type,asin,tags,review
0,The Killing Kind,John Connolly,Book,0340771224,,i still haven't had time to read this one...
0,The Third Secret,Steve Berry,Book,0340899263,,need to find time to read this book
3,The Last Templar,Raymond Khoury,Book,0752880705,,
5,The Traveller,John Twelve Hawks,Book,059305430X,,
4,Crisis Four,Andy Mcnab,Book,0345428080,,
5,Prey,Michael Crichton,Book,0007154534,,
CSV;

        $this->reader = Reader::fromString($csv);
        $this->reader->setHeaderOffset(0);
    }

    #[Test]
    public function it_will_be_triggered_via_property_usage_using_the_record_offset(): void
    {
        $foobar = new class (5, 'title', 'author', 'type', 'asin', 'tags') {
            private string $review;

            public function __construct(
                public readonly int $rating,
                public readonly string $title,
                public readonly string $author,
                public readonly string $type,
                public readonly string $asin,
                #[MapCell(column: 5)]
                public readonly string $tags,
            ) {
            }

            public function setReview(string $foobar): void
            {
                $this->review = $foobar;
            }

            public function getReview(): string
            {
                return $this->review;
            }
        };

        $this->expectException(TypeCastingFailed::class);
        $this->expectExceptionMessageMatches('/Casting the property `'.preg_quote($foobar::class, '/').'::tags` using the record field offset `5` failed;/');

        $this->reader->nthAsObject(2, $foobar::class);
    }

    #[Test]
    public function it_will_be_triggered_via_property_usage_using_the_record_name(): void
    {
        $foobar = new class (5, 'title', 'author', 'type', 'asin', 'tags') {
            private string $review;

            public function __construct(
                public readonly int $rating,
                public readonly string $title,
                public readonly string $author,
                public readonly string $type,
                public readonly string $asin,
                public readonly string $tags,
            ) {
            }

            public function setReview(string $foobar): void
            {
                $this->review = $foobar;
            }

            public function getReview(): string
            {
                return $this->review;
            }
        };

        $this->expectException(TypeCastingFailed::class);
        $this->expectExceptionMessageMatches('/Casting the property `'.preg_quote($foobar::class, '/').'::tags` using the record field `tags` failed;/');

        $this->reader->nthAsObject(2, $foobar::class);
    }

    #[Test]
    public function it_will_be_triggered_via_method_usage_using_the_record_name(): void
    {
        $foobar = new class (5, 'title', 'author', 'type', 'asin', 'tags') {
            private string $review;

            public function __construct(
                public readonly int $rating,
                public readonly string $title,
                public readonly string $author,
                public readonly string $type,
                public readonly string $asin,
                public readonly ?string $tags,
            ) {
            }

            public function setReview(string $foobar): void
            {
                $this->review = $foobar;
            }

            public function getReview(): string
            {
                return $this->review;
            }
        };

        $this->expectException(TypeCastingFailed::class);
        $this->expectExceptionMessageMatches('/Casting the first argument `foobar` of the method `'.preg_quote($foobar::class, '/').'::setReview\(\)` using the record field `review` failed;/');

        $this->reader->nthAsObject(2, $foobar::class);
    }

    #[Test]
    public function it_will_be_triggered_via_method_usage_using_the_record_offset(): void
    {
        $foobar = new class (5, 'title', 'author', 'type', 'asin', 'tags') {
            private string $review;

            public function __construct(
                public readonly int $rating,
                public readonly string $title,
                public readonly string $author,
                public readonly string $type,
                public readonly string $asin,
                public readonly ?string $tags,
            ) {
            }

            #[MapCell(column: 6)]
            public function setReview(string $foobar): void
            {
                $this->review = $foobar;
            }

            public function getReview(): string
            {
                return $this->review;
            }
        };

        $this->expectException(TypeCastingFailed::class);
        $this->expectExceptionMessageMatches('/Casting the first argument `foobar` of the method `'.preg_quote($foobar::class, '/').'::setReview\(\)` using the record field offset `6` failed;/');

        $this->reader->nthAsObject(2, $foobar::class);
    }
}
