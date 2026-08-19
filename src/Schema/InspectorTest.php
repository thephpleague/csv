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

namespace League\Csv\Schema;

use League\Csv\Reader;
use PHPUnit\Framework\TestCase;
use ValueError;

use function array_map;
use function implode;
use function rand;
use function random_int;
use function str_repeat;
use function str_shuffle;
use function substr;

final class InspectorTest extends TestCase
{
    private function csv(string $content): Reader
    {
        $reader = Reader::fromString($content);
        $reader->setHeaderOffset(0);
        $reader->setDelimiter(';');

        return $reader;
    }

    public function testConstructorRejectsInvalidSampleLimit(): void
    {
        $this->expectException(ValueError::class);

        new Inspector(0); /* @phpstan-ignore-line */
    }

    public function testWithSampleLimitReturnsSameInstanceIfUnchanged(): void
    {
        $inspector = new Inspector(10);

        self::assertSame($inspector, $inspector->withSampleLimit(10));
    }

    public function testWithSampleLimitReturnsNewInstanceIfChanged(): void
    {
        $inspector = new Inspector(10);
        $new = $inspector->withSampleLimit(5);

        self::assertNotSame($inspector, $new);
        self::assertSame(5, $new->sampleLimit);
    }

    public function testWithFieldsReturnsNewInstance(): void
    {
        $inspector = new Inspector(10);
        $fieldList = new FieldList();

        $new = $inspector->withFields($fieldList);

        self::assertNotSame($inspector, $new);
        self::assertSame($fieldList, $new->fieldList);
    }

    public function testDefaultFactory(): void
    {
        $inspector = Inspector::default(20);

        self::assertSame(20, $inspector->sampleLimit);
        self::assertCount(3, $inspector->fieldList);
    }

    public function testSchemaFallsBackToStringFieldWhenNoMatch(): void
    {
        $csv = $this->csv(<<<CSV
name;value
foo;???
bar;###
CSV);
        $schema = $csv->inferSchema(Inspector::default());

        self::assertSame(['name' => 'string', 'value' => 'string'], $schema->types());
    }

    public function testSchemaDetectsNumericField(): void
    {
        $csv = $this->csv(<<<CSV
age
10
20
30
CSV);
        $schema = $csv->inferSchema(new Inspector(10, new FieldList(new NumericField())));

        self::assertSame(['age' => 'numeric'], $schema->types());
    }

    public function testSchemaIgnoresEmptyValues(): void
    {
        $csv = $this->csv(<<<CSV
age
10

20

30
CSV);
        $schema = $csv->inferSchema(new Inspector(10, new FieldList(new NumericField())));

        self::assertSame('numeric', $schema->get('age')->name());
    }

    public function testSchemaRespectsSampleLimit(): void
    {
        $csv = $this->csv(<<<CSV
value
1
2
3
not-a-number
CSV);
        $schema = $csv->inferSchema(new Inspector(2, new FieldList(new NumericField())));

        self::assertSame('numeric', $schema->get('value')->name());
    }

    public function testSchemaChoosesBestScoringField(): void
    {
        $csv = $this->csv(<<<CSV
value
1
2
foo
CSV);
        $fieldList = new FieldList(new NumericField(), new StringField());
        $schema = $csv->inferSchema(new Inspector(10, $fieldList));

        self::assertSame('string', $schema->get('value')->name());
    }

    /*******************
     * FUZZY Tests
     *******************/

    private function csvFromRows(array $rows): Reader
    {
        $content = implode(
            "\n",
            array_map(
                fn (array $row): string => implode(';', $row),
                $rows
            )
        );

        return $this->csv($content);
    }

    private function randomString(): string
    {
        return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 5)), 0, random_int(1, 10));
    }

    private function randomValue(): mixed
    {
        return match (rand(0, 5)) {
            0 => random_int(0, 1000),            // int
            1 => random_int(0, 1000) / 10,       // float
            2 => (string) random_int(0, 1000),   // numeric string
            3 => $this->randomString(),          // random string
            4 => '',                             // empty string
            default => null,
        };
    }

    public function testFuzzSchemaDoesNotCrash(): void
    {
        $inspector = Inspector::default();
        $columns = ['col1', 'col2', 'col3'];
        $rows = [$columns];
        for ($i = 0; $i < 50; $i++) {
            for ($r = 0; $r < rand(1, 20); $r++) {
                $rows[] = [
                    $this->randomValue(),
                    $this->randomValue(),
                    $this->randomValue(),
                ];
            }

            $csv = $this->csvFromRows($rows);

            self::assertSame($columns, $inspector->schema($csv)->names());
        }

    }

    public function testFuzzTypesAreAlwaysNonEmptyStrings(): void
    {
        $inspector = Inspector::default();

        for ($i = 0; $i < 50; $i++) {
            $columns = ['a', 'b'];

            $rows = [$columns];

            for ($r = 0; $r < rand(1, 20); $r++) {
                $rows[] = [
                    $this->randomValue(),
                    $this->randomValue(),
                ];
            }

            $schema = $inspector->schema($this->csvFromRows($rows));

            foreach ($schema->types() as $type) {
                self::assertIsString($type);
                self::assertNotSame('', $type);
            }
        }
    }

    public function testFuzzEmptyColumnsFallbackToString(): void
    {
        $inspector = Inspector::default();

        $rows = [
            ['col'],
            ['', null, '', null],
        ];

        $schema = $inspector->schema($this->csvFromRows($rows));

        self::assertSame('string', $schema->get('col')->name());
    }

    public function testFuzzNumericColumnsDetected(): void
    {
        $inspector = new Inspector(50, new FieldList(new NumericField(), new StringField()));

        for ($i = 0; $i < 30; $i++) {
            $rows = [
                ['num'],
            ];

            for ($r = 0; $r < rand(5, 20); $r++) {
                $rows[] = [rand(0, 1000)];
            }

            $schema = $inspector->schema($this->csvFromRows($rows));

            self::assertSame('numeric', $schema->get('num')->name());
        }
    }

    public function testFuzzMixedDataPrefersString(): void
    {
        $inspector = new Inspector(50, new FieldList(new NumericField(), new StringField()));

        for ($i = 0; $i < 30; $i++) {
            $rows = [
                ['mixed'],
            ];

            for ($r = 0; $r < 20; $r++) {
                $rows[] = [
                    1 === rand(0, 1)
                        ? rand(0, 100)
                        : $this->randomString(),
                ];
            }

            $schema = $inspector->schema($this->csvFromRows($rows));

            self::assertSame('string', $schema->get('mixed')->name());
        }
    }

    public function testFuzzSampleLimitDoesNotBreakInference(): void
    {
        $fieldList = new FieldList(new NumericField(), new StringField());
        for ($limit = 1; $limit <= 10; $limit++) {
            $inspector = new Inspector($limit, $fieldList);

            $rows = [
                ['value'],
            ];

            for ($i = 0; $i < 50; $i++) {
                $rows[] = [random_int(0, 100)];
            }

            $schema = $inspector->schema($this->csvFromRows($rows));

            self::assertSame('numeric', $schema->get('value')->name());
        }
    }
}
