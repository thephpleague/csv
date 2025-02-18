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

use League\Csv\Buffer;
use League\Csv\Query\Constraint\Offset;
use League\Csv\Writer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Buffer::class)]
final class BufferTest extends TestCase
{
    #[Test]
    public function it_will_create_a_datable_with_a_header(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer([
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        ], $header);

        self::assertSame($header, $dataTable->getHeader());
        self::assertSame(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'],
            $dataTable->nth(0)
        );
        self::assertSame(6, $dataTable->recordCount());

        $weather = new class (new DateTimeImmutable(), 6, 'Brussels') {
            public function __construct(
                public readonly DateTimeImmutable $date,
                public readonly int $temperature,
                public readonly string $place,
            ) {
            }
        };

        $obj = $dataTable->nthAsObject(0, $weather::class);
        self::assertInstanceOf($weather::class, $obj);
        self::assertSame('2011-01-01', $obj->date->format('Y-m-d'));
    }

    #[Test]
    public function it_will_create_a_datable_without_a_header(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        ]);

        self::assertSame([], $dataTable->getHeader());
        self::assertSame(['2011-01-01', '1', 'Galway'], $dataTable->nth(0));
        self::assertSame([], $dataTable->nth(42));
        self::assertSame(6, $dataTable->recordCount());

        $weather = new class (new DateTimeImmutable(), 6, 'Brussels') {
            public function __construct(
                public readonly DateTimeImmutable $date,
                public readonly int $temperature,
                public readonly string $place,
            ) {
            }
        };

        $obj = $dataTable->nthAsObject(0, $weather::class, ['date', 'temperature', 'place']);
        self::assertInstanceOf($weather::class, $obj);
        self::assertSame('2011-01-01', $obj->date->format('Y-m-d'));
        self::assertNull($dataTable->nthAsObject(42, $weather::class, ['date', 'temperature', 'place']));

        $collection = $dataTable->getRecordsAsObject($weather::class, ['date', 'temperature', 'place']);
        $collection = iterator_to_array($collection);
        self::assertInstanceOf($weather::class, $collection[0]);
        self::assertSame('2011-01-01', $collection[0]->date->format('Y-m-d'));

        $mappedCollection = $dataTable->map(
            fn (array $item) => new ($weather::class)(new DateTimeImmutable($item[0]), (int) $item[1], (string) $item[2])
        );

        $mappedCollection = iterator_to_array($mappedCollection);
        self::assertInstanceOf($weather::class, $mappedCollection[0]);
        self::assertSame('2011-01-01', $mappedCollection[0]->date->format('Y-m-d'));
    }

    #[Test]
    public function it_will_only_consider_header_content_and_not_the_record_keys_and_values_1(): void
    {
        $header = ['date', 'temperature'];
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1'],
            ['date' => '2011-01-02', 'temperature' => '-1'],
            ['date' => '2011-01-03', 'temperature' => '0'],
            ['date' => '2011-01-01', 'temperature' => '6'],
            ['date' => '2011-01-02', 'temperature' => '8'],
            ['date' => '2011-01-03', 'temperature' => '5'],
        ], $header);

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1'], $dataTable->nth(0));
        self::assertSame($header, $dataTable->getHeader());
        self::assertSame(6, $dataTable->recordCount());
    }

    #[Test]
    public function it_will_only_consider_header_content_and_not_the_record_keys_and_values_2(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], $header);

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'], $dataTable->nth(0));
        self::assertSame($header, $dataTable->getHeader());
        self::assertSame(6, $dataTable->recordCount());
    }

    #[Test]
    public function it_will_return_no_rows_if_non_rows_are_supplied(): void
    {
        $emptyDataTable = new Buffer();

        self::assertSame([], $emptyDataTable->getHeader());
        self::assertSame(0, $emptyDataTable->recordCount());

        $header = ['foo', 'bar', 'baz'];
        $emptyDataTableWithHeader = new Buffer(header: $header);

        self::assertSame($header, $emptyDataTableWithHeader->getHeader());
        self::assertSame(0, $emptyDataTableWithHeader->recordCount());
    }

    #[Test]
    public function it_can_be_filled_by_inserting_new_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);
        self::assertSame(2, $dataTable->insertAll([
            ['2011-01-01', '1', 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
        ]));

        self::assertSame($header, $dataTable->getHeader());
        self::assertSame(2, $dataTable->recordCount());
        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'], $dataTable->nth(0));
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_list_due_to_missing_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(\ValueError::class);
        $dataTable->insertOne(['2011-01-01', '1']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_missing_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(\ValueError::class);
        $dataTable->insertOne(['date' => '2011-01-01', 'temperature' => '1']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_unknown_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(\ValueError::class);
        $dataTable->insertOne(['date' => '2011-01-01', 'temperature' => '1', 'location' => 'Berkeley']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_extra_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(\ValueError::class);
        $dataTable->insertOne(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley', 'origin' => 'station']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_mixed_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(\ValueError::class);
        $dataTable->insertOne(['date' => '2011-01-01', '1', 'place' => 'Berkeley', 'origin' => 'station']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_on_buffer_without_header(): void
    {
        $dataTable = new Buffer();
        $this->expectException(\ValueError::class);
        $dataTable->insertAll([['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley', 'origin' => 'station']]);
    }

    #[Test]
    public function it_can_be_filled_by_inserting_new_record_on_buffer_without_header(): void
    {
        $dataTable = new Buffer();
        $dataTable->insertOne(['2011-01-01', '1', 'Berkeley', 'station']);
        self::assertSame(1, $dataTable->recordCount());
    }

    #[Test]
    public function it_can_be_updated_by_replacing_or_removing_records_using_its_offset(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        ], $header);

        self::assertSame(1, $dataTable->update(0, ['date' => '2025-02-08', 'temperature' => '3']));
        self::assertSame(2, $dataTable->delete([4, 5, 4]));

        self::assertSame($header, $dataTable->getHeader());
        self::assertSame(4, $dataTable->recordCount());
        self::assertSame(['date' => '2025-02-08', 'temperature' => '3', 'place' => 'Berkeley'], $dataTable->nth(0));
    }

    #[Test]
    public function it_can_not_push_invalid_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(ValueError::class);

        $dataTable->insertAll([['foo' => 'bar']]);
    }

    #[Test]
    public function it_can_not_unshift_invalid_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $dataTable = new Buffer(header: $header);

        $this->expectException(ValueError::class);

        $dataTable->insertAll([['foo' => 'bar']]);
    }

    #[Test]
    public function it_can_allow_removing_no_records_or_all_records(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], ['date', 'temperature', 'place']);

        $this->expectException(OutOfBoundsException::class);
        $dataTable->delete(42);
    }

    #[Test]
    public function it_can_be_used_with_sqlite3(): void
    {
        $db = new SQLite3('');
        $tableCreateQuery = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        );
SQL;
        $db->exec($tableCreateQuery);
        $seedData = [
            ['name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ['name' => 'Bobby', 'email' => 'bobby@example.com'],
            ['name' => 'Ricky', 'email' => 'ricky@example.com'],
            ['name' => 'Mike', 'email' => 'mike@example.com'],
            ['name' => 'Ralph', 'email' => 'ralph@example.com'],
            ['name' => 'Johnny', 'email' => 'johnny@example.com'],
        ];

        $stmt = $db->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new SQLite3Exception('Unable to prepare statement');
        }

        foreach ($seedData as $data) {
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->execute();
        }

        /** @var SQLite3Stmt $stmt */
        $stmt = $db->prepare('SELECT * FROM users');
        /** @var SQLite3Result $result */
        $result = $stmt->execute();
        $tabularData = Buffer::from($result);

        self::assertSame(['id', 'name', 'email'], $tabularData->getHeader());
        self::assertSame(6, $tabularData->recordCount());
        self::assertSame(
            [1, 'Ronnie', 'ronnie@example.com'],
            Buffer::from($tabularData, false)->nth(0)
        );
    }

    #[Test]
    public function it_can_be_used_with_sqlite3_and_strip_header(): void
    {
        $db = new SQLite3('');
        $tableCreateQuery = <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        );
SQL;
        $db->exec($tableCreateQuery);
        $seedData = [
            ['name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ['name' => 'Bobby', 'email' => 'bobby@example.com'],
            ['name' => 'Ricky', 'email' => 'ricky@example.com'],
            ['name' => 'Mike', 'email' => 'mike@example.com'],
            ['name' => 'Ralph', 'email' => 'ralph@example.com'],
            ['name' => 'Johnny', 'email' => 'johnny@example.com'],
        ];

        $stmt = $db->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        if (!$stmt instanceof SQLite3Stmt) {
            throw new SQLite3Exception('Unable to prepare statement');
        }

        foreach ($seedData as $data) {
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->execute();
        }

        /** @var SQLite3Stmt $stmt */
        $stmt = $db->prepare('SELECT * FROM users');
        /** @var SQLite3Result $result */
        $result = $stmt->execute();
        $tabularData = Buffer::from($result, false);

        self::assertSame([], $tabularData->getHeader());
        self::assertSame(6, $tabularData->recordCount());
        self::assertSame(
            [1, 'Ronnie', 'ronnie@example.com'],
            Buffer::from($tabularData)->nth(0)
        );
    }

    #[Test]
    public function it_can_be_used_with_pdo(): void
    {
        $connection = new PDO('sqlite::memory:');
        $tableCreateQuery = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    );
SQL;
        $connection->exec($tableCreateQuery);
        $seedData = [
            ['name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ['name' => 'Bobby', 'email' => 'bobby@example.com'],
            ['name' => 'Ricky', 'email' => 'ricky@example.com'],
            ['name' => 'Mike', 'email' => 'mike@example.com'],
            ['name' => 'Ralph', 'email' => 'ralph@example.com'],
            ['name' => 'Johnny', 'email' => 'johnny@example.com'],
        ];

        $stmt = $connection->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        foreach ($seedData as $data) {
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->execute();
        }

        $stmt = $connection->prepare('SELECT * FROM users');
        $stmt->execute();
        $tabularData = Buffer::from($stmt);

        self::assertSame(['id', 'name', 'email'], $tabularData->getHeader());
        self::assertSame(6, $tabularData->recordCount());
        self::assertSame(['id' => 1, 'name' => 'Ronnie', 'email' => 'ronnie@example.com'], $tabularData->nth(0));
    }

    #[Test]
    public function it_can_be_used_with_pdo_without_storing_the_header(): void
    {
        $connection = new PDO('sqlite::memory:');
        $tableCreateQuery = <<<SQL
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE
    );
SQL;
        $connection->exec($tableCreateQuery);
        $seedData = [
            ['name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ['name' => 'Bobby', 'email' => 'bobby@example.com'],
            ['name' => 'Ricky', 'email' => 'ricky@example.com'],
            ['name' => 'Mike', 'email' => 'mike@example.com'],
            ['name' => 'Ralph', 'email' => 'ralph@example.com'],
            ['name' => 'Johnny', 'email' => 'johnny@example.com'],
        ];

        $stmt = $connection->prepare('INSERT INTO users (name, email) VALUES (:name, :email)');
        foreach ($seedData as $data) {
            $stmt->bindValue(':name', $data['name']);
            $stmt->bindValue(':email', $data['email']);
            $stmt->execute();
        }

        $stmt = $connection->prepare('SELECT * FROM users');
        $stmt->execute();
        $tabularData = Buffer::from($stmt, false);

        self::assertSame([], $tabularData->getHeader());
        self::assertSame(6, $tabularData->recordCount());
        self::assertSame([1, 'Ronnie', 'ronnie@example.com'], $tabularData->nth(0));
    }

    #[Test]
    public function it_will_fail_with_bogus_predicate(): void
    {
        $this->expectException(TypeError::class);

        (new Buffer())->delete(['foo' => 'bar']);
    }

    #[Test]
    public function it_implements_array_access_getter_methods_with_header(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => null],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        ], ['date', 'temperature', 'place']);

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => null], $dataTable->nth(0));
        self::assertNotEmpty($dataTable->nth(5));
        self::assertEmpty($dataTable->nth(42));
    }

    #[Test]
    public function it_implements_array_access_getter_methods_without_header(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
            ['2011-01-03', '0'],
            ['2011-01-01', '6'],
            ['2011-01-02', '8'],
            ['2011-01-03', '5'],
        ]);

        self::assertSame(['2011-01-01', '1'], $dataTable->nth(0));
        self::assertNotEmpty($dataTable->nth(5));
        self::assertEmpty($dataTable->nth(42));
    }

    #[Test]
    public function it_can_update_the_buffer_using_a_record_as_a_list(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], ['date', 'temperature', 'place']);

        self::assertSame(0, $dataTable->update(Offset::filterOn('=', 42), ['2011-01-01', '1', 'bujumbura']));
        self::assertSame(1, $dataTable->update(0, ['2011-01-01', '1', 'bujumbura']));
        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'bujumbura'], $dataTable->nth(0));
    }

    #[Test]
    public function it_can_delete_the_buffer_records_using_a_closure(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        ], ['date', 'temperature', 'place']);

        self::assertSame(3, $dataTable->delete(fn (array $record, int $offset): bool => 0 === $offset % 2));
        self::assertSame(3, $dataTable->recordCount());
    }

    #[Test]
    public function it_can_return_a_column_by_name(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], ['date', 'temperature', 'place']);

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($dataTable->fetchColumn('date'))
        );
    }

    #[Test]
    public function it_can_return_a_column_by_offset(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
            ['2011-01-03', '0'],
            ['2011-01-01', '6'],
            ['2011-01-02', '8'],
            ['2011-01-03', '5'],
        ]);

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($dataTable->fetchColumn())
        );
    }

    #[Test]
    public function it_can_return_a_column_by_offset_even_when_theres_a_header(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], ['date', 'temperature', 'place']);

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($dataTable->fetchColumn())
        );
    }

    #[Test]
    public function it_will_store_the_buffer_with_its_header(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        ], ['date', 'temperature']);

        $writer = Writer::createFromString();
        $res = $dataTable->to($writer);

        self::assertSame(44, $res);
        self::assertSame("date,temperature\n2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_will_store_the_buffer_without_its_header(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        ], ['date', 'temperature']);

        $writer = Writer::createFromString();
        $res = $dataTable->to($writer, false);

        self::assertSame(27, $res);
        self::assertSame("2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_will_store_the_buffer_without_its_header_if_none_exists(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        ]);

        $writer = Writer::createFromString();
        $res = $dataTable->to($writer);

        self::assertSame(27, $res);
        self::assertSame("2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_can_return_the_column_pairs_when_the_buffer_has_a_header(): void
    {
        $dataTable = new Buffer([
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        ], ['date', 'temperature', 'place']);

        $expected = [
            '1' => 'Berkeley',
            '-1' => 'Berkeley',
            '0' => 'Galway',
            '6' => 'Berkeley',
            '8' => 'Galway',
            '5' => 'Berkeley',
        ];

        self::assertSame($expected, iterator_to_array($dataTable->fetchPairs(1, 2)));
        self::assertSame($expected, iterator_to_array($dataTable->fetchPairs('temperature', 'place')));
        self::assertSame($expected, iterator_to_array($dataTable->fetchPairs(1, 'place')));
        self::assertSame($expected, iterator_to_array($dataTable->fetchPairs('temperature', 2)));
    }

    #[Test]
    public function it_can_return_the_column_pairs_when_the_buffer_has_no_header(): void
    {
        $dataTable = new Buffer([
            ['2011-01-01', '1', 'Berkeley'],
            ['2011-01-02', '-1', 'Berkeley'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Galway'],
            ['2011-01-03', '5', 'Berkeley'],
        ]);

        $expected = [
            '1' => 'Berkeley',
            '-1' => 'Berkeley',
            '0' => 'Galway',
            '6' => 'Berkeley',
            '8' => 'Galway',
            '5' => 'Berkeley',
        ];

        self::assertSame($expected, iterator_to_array($dataTable->fetchPairs(1, 2)));
    }
}
