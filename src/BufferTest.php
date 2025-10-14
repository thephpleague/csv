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
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use League\Csv\Query\Constraint\Column;
use League\Csv\Query\Constraint\Offset;
use League\Csv\Reader;
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
        $buffer = new Buffer($header);
        $buffer->insert(
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        );

        self::assertSame($header, $buffer->getHeader());
        self::assertSame(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'],
            $buffer->nth(0)
        );
        self::assertSame(6, $buffer->recordCount());
        self::assertTrue($buffer->hasHeader());
        self::assertFalse($buffer->isEmpty());

        $weather = new class (new DateTimeImmutable(), 6, 'Brussels') {
            public function __construct(
                public readonly DateTimeImmutable $date,
                public readonly int $temperature,
                public readonly string $place,
            ) {
            }
        };

        $objStart = $buffer->nthAsObject(0, $weather::class);
        $objEnd = $buffer->nthAsObject($buffer->recordCount() - 1, $weather::class);
        $objFirst = $buffer->firstAsObject($weather::class);
        $objLast = $buffer->lastAsObject($weather::class);

        self::assertInstanceOf($weather::class, $objStart);
        self::assertInstanceOf($weather::class, $objFirst);
        self::assertInstanceOf($weather::class, $objLast);
        self::assertSame('2011-01-01', $objStart->date->format('Y-m-d'));
        self::assertEquals($objFirst, $objStart);
        self::assertEquals($objLast, $objEnd);
    }

    #[Test]
    public function it_will_create_a_datable_without_a_header(): void
    {
        $buffer = new Buffer();
        self::assertSame([], $buffer->last());
        self::assertSame([], $buffer->first());
        self::assertNull($buffer->nthAsObject(23, stdClass::class));

        $buffer->insert(
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Galway'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Berkeley'],
            ['2011-01-03', '5', 'Berkeley'],
        );

        self::assertSame([], $buffer->getHeader());
        self::assertFalse($buffer->hasHeader());
        self::assertFalse($buffer->isEmpty());
        self::assertSame(['2011-01-01', '1', 'Galway'], $buffer->nth(0));
        self::assertSame([], $buffer->nth(42));
        self::assertSame(6, $buffer->recordCount());

        $weather = new class (new DateTimeImmutable(), 6, 'Brussels') {
            public function __construct(
                public readonly DateTimeImmutable $date,
                public readonly int $temperature,
                public readonly string $place,
            ) {
            }
        };

        $obj = $buffer->firstAsObject($weather::class, ['date', 'temperature', 'place']);
        self::assertInstanceOf($weather::class, $obj);
        self::assertSame('2011-01-01', $obj->date->format('Y-m-d'));
        self::assertNull($buffer->nthAsObject(42, $weather::class, ['date', 'temperature', 'place']));

        $collection = $buffer->getRecordsAsObject($weather::class, ['date', 'temperature', 'place']);
        $collection = iterator_to_array($collection);
        self::assertInstanceOf($weather::class, $collection[0]);
        self::assertSame('2011-01-01', $collection[0]->date->format('Y-m-d'));

        $mappedCollection = $buffer->map(
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
        $buffer = new Buffer($header);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1'],
            ['date' => '2011-01-02', 'temperature' => '-1'],
            ['date' => '2011-01-03', 'temperature' => '0'],
            ['date' => '2011-01-01', 'temperature' => '6'],
            ['date' => '2011-01-02', 'temperature' => '8'],
            ['date' => '2011-01-03', 'temperature' => '5'],
        );

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1'], $buffer->nth(0));
        self::assertSame($header, $buffer->getHeader());
        self::assertSame(6, $buffer->recordCount());
        self::assertSame(['date' => '2011-01-03', 'temperature' => '5'], $buffer->last());
        self::assertSame(['date' => '2011-01-01', 'temperature' => '1'], $buffer->first());
    }

    #[Test]
    public function it_will_only_consider_header_content_and_not_the_record_keys_and_values_2(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        );

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'], $buffer->nth(0));
        self::assertSame($header, $buffer->getHeader());
        self::assertSame(6, $buffer->recordCount());
    }

    #[Test]
    public function it_will_return_no_rows_if_non_rows_are_supplied(): void
    {
        $emptyDataTable = new Buffer();
        self::assertFalse($emptyDataTable->hasHeader());
        self::assertTrue($emptyDataTable->isEmpty());
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
        $buffer = new Buffer($header);
        self::assertSame(2, $buffer->insert(
            ['2011-01-01', '1', 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
        ));

        self::assertSame($header, $buffer->getHeader());
        self::assertSame(2, $buffer->recordCount());
        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'], $buffer->nth(0));
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_list_due_to_missing_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['2011-01-01', '1']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_missing_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['date' => '2011-01-01', 'temperature' => '1']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_unknown_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['date' => '2011-01-01', 'temperature' => '1', 'location' => 'Berkeley']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_extra_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley', 'origin' => 'station']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_with_mixed_fields(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['date' => '2011-01-01', '1', 'place' => 'Berkeley', 'origin' => 'station']);
    }

    #[Test]
    public function it_can_not_be_filled_by_inserting_new_invalid_record_on_buffer_without_header(): void
    {
        $buffer = new Buffer();
        $buffer->insert(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley', 'origin' => 'station']);

        self::assertSame(1, $buffer->recordCount());
        self::assertSame(['2011-01-01', '1', 'Berkeley', 'station'], $buffer->nth(0));
    }

    #[Test]
    public function it_can_be_filled_by_inserting_new_record_on_buffer_without_header(): void
    {
        $buffer = new Buffer();
        $buffer->insert(['2011-01-01', '1', 'Berkeley', 'station']);
        self::assertSame(1, $buffer->recordCount());
    }

    #[Test]
    public function it_can_be_updated_by_replacing_or_removing_records_using_its_offset(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
        );
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        );

        self::assertSame(1, $buffer->update(fn (array $row, int $offset): bool => 0 === $offset, ['date' => '2025-02-08', 'temperature' => '3']));
        self::assertSame(2, $buffer->delete(fn (array $row, int $offset): bool => in_array($offset, [4, 5, 4], true)));

        self::assertSame($header, $buffer->getHeader());
        self::assertSame(4, $buffer->recordCount());
        self::assertSame(['date' => '2025-02-08', 'temperature' => '3', 'place' => 'Berkeley'], $buffer->nth(0));
    }

    #[Test]
    public function it_can_not_insert_invalid_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);

        $buffer->insert(['foo' => 'bar']);
    }

    #[Test]
    public function it_can_not_insert_without_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert();
    }

    #[Test]
    public function it_can_not_unshift_invalid_records(): void
    {
        $header = ['date', 'temperature', 'place'];
        $buffer = new Buffer($header);

        $this->expectException(CannotInsertRecord::class);

        $buffer->insert(['foo' => 'bar']);
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
            Buffer::from($tabularData, Buffer::EXCLUDE_HEADER)->nth(0)
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
        $tabularData = Buffer::from($result, Buffer::EXCLUDE_HEADER);

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
        $tabularData = Buffer::from($stmt, Buffer::EXCLUDE_HEADER);

        self::assertSame([], $tabularData->getHeader());
        self::assertSame(6, $tabularData->recordCount());
        self::assertSame([1, 'Ronnie', 'ronnie@example.com'], $tabularData->nth(0));
    }

    #[Test]
    public function it_implements_array_access_getter_methods_with_header(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => null],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        );

        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => null], $buffer->nth(0));
        self::assertNotEmpty($buffer->nth(5));
        self::assertEmpty($buffer->nth(42));
    }

    #[Test]
    public function it_throws_an_exception_on_negative_offset(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $this->expectException(InvalidArgument::class);

        $buffer->nth(-1); /* @phpstan-ignore-line */
    }

    #[Test]
    public function it_returns_an_empty_array_if_no_record_is_present(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);

        self::assertSame([], $buffer->nth(0));
        self::assertSame([], $buffer->nth(42));
    }

    #[Test]
    public function it_implements_array_access_getter_methods_without_header(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
            ['2011-01-03', '0'],
            ['2011-01-01', '6'],
            ['2011-01-02', '8'],
            ['2011-01-03', '5'],
        );

        self::assertSame(['2011-01-01', '1'], $buffer->nth(0));
        self::assertNotEmpty($buffer->nth(5));
        self::assertEmpty($buffer->nth(42));
    }

    #[Test]
    public function it_can_update_the_buffer_using_a_record_as_a_list(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        );

        self::assertSame(0, $buffer->update(Offset::filterOn('=', 42), ['2011-01-01', '1', 'bujumbura']));
        self::assertSame(1, $buffer->update(Offset::filterOn('=', 0), ['2011-01-01', '1', 'bujumbura']));
        self::assertSame(['date' => '2011-01-01', 'temperature' => '1', 'place' => 'bujumbura'], $buffer->nth(0));
    }

    #[Test]
    public function it_can_update_the_buffer_using_a_record_as_a_list_on_a_buffer_without_header(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['2011-01-01', '1', 'Galway'],
            ['2011-01-02', '-1', 'Berkeley'],
            ['2011-01-03', '0', 'Galway'],
            ['2011-01-01', '6', 'Berkeley'],
            ['2011-01-02', '8', 'Galway'],
            ['2011-01-03', '5', 'Berkeley'],
        );

        self::assertSame(0, $buffer->update(Offset::filterOn('=', 42), ['2011-01-01', '1', 'bujumbura']));
        self::assertSame(1, $buffer->update(Offset::filterOn('=', 0), [2 => 'bujumbura']));
        self::assertSame(['2011-01-01', '1', 'bujumbura'], $buffer->nth(0));
    }

    #[Test]
    public function it_can_delete_the_buffer_records_using_a_closure(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        );

        self::assertSame(3, $buffer->delete(fn (array $record, int $offset): bool => 0 === $offset % 2));
        self::assertSame(3, $buffer->recordCount());
    }

    #[Test]
    public function it_can_return_a_column_by_name(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        );

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($buffer->fetchColumn('date'))
        );
    }

    #[Test]
    public function it_can_return_a_column_by_offset(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
            ['2011-01-03', '0'],
            ['2011-01-01', '6'],
            ['2011-01-02', '8'],
            ['2011-01-03', '5'],
        );

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($buffer->fetchColumn())
        );
    }

    #[Test]
    public function it_can_return_a_column_by_offset_even_when_theres_a_header(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Berkeley'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Berkeley'],
        );

        self::assertSame(
            ['2011-01-01', '2011-01-02', '2011-01-03', '2011-01-01', '2011-01-02', '2011-01-03'],
            iterator_to_array($buffer->fetchColumn())
        );
    }

    #[Test]
    public function it_will_store_the_buffer_with_its_header(): void
    {
        $buffer = new Buffer(['date', 'temperature']);
        $buffer->insert(
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        );

        $writer = Writer::fromString();
        $res = $buffer->to($writer);

        self::assertSame(44, $res);
        self::assertSame("date,temperature\n2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_will_store_the_buffer_without_its_header(): void
    {
        $buffer = new Buffer(['date', 'temperature']);
        $buffer->insert(
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        );

        $writer = Writer::fromString();
        $res = $buffer->to($writer, Buffer::EXCLUDE_HEADER);

        self::assertSame(27, $res);
        self::assertSame("2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_will_store_the_buffer_without_its_header_if_none_exists(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['2011-01-01', '1'],
            ['2011-01-02', '-1'],
        );

        $writer = Writer::fromString();
        $res = $buffer->to($writer);

        self::assertSame(27, $res);
        self::assertSame("2011-01-01,1\n2011-01-02,-1\n", $writer->toString());
    }

    #[Test]
    public function it_can_validate_a_record_on_insertion(): void
    {
        $buffer = new Buffer();
        $buffer->addValidator(fn (array $row): bool => $row[1] >= 0, 'func1');

        $this->expectExceptionObject(CannotInsertRecord::triggerOnValidation('func1', ['column1', -1]));

        $buffer->insert(['column1', 1]);
        $buffer->insert(['column1', -1]);
    }

    #[Test]
    public function it_can_validate_a_record_on_update(): void
    {
        $buffer = new Buffer();
        $buffer->addValidator(fn (array $row): bool => $row[1] >= 0, 'func1');

        $this->expectExceptionObject(CannotInsertRecord::triggerOnValidation('func1', ['column1', -1]));

        $buffer->insert(['column1', 1]);
        $buffer->update(fn (array $row, int $offset): bool => 0 === $offset, [1 => -1]);
    }

    #[Test]
    public function it_can_re_order_the_fields_using_the_header(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['moko', 'mibalé', 'misató'],
            ['un', 'deux', 'trois'],
            ['one', 'two', 'three'],
            ['unos', 'dos', 'tres'],
        );

        $res = iterator_to_array($buffer->getRecords([2 => 'column 1', 1 => 'column 2', 0 => 'column 3']));
        self::assertSame(['column 1' => 'misató', 'column 2' => 'mibalé', 'column 3' => 'moko'], $res[0]);
    }

    #[Test]
    public function it_fails_to_update_a_record_with_no_record(): void
    {
        $buffer = new Buffer();
        $buffer->insert(
            ['moko', 'mibalé', 'misató'],
            ['un', 'deux', 'trois'],
            ['one', 'two', 'three'],
            ['unos', 'dos', 'tres'],
        );

        $this->expectException(CannotInsertRecord::class);
        $buffer->update(fn (array $row, int $offset): bool => 1 === $offset, []);
    }

    #[Test]
    public function it_can_format_the_data_inserted(): void
    {
        $buffer = new Buffer();
        $buffer->addFormatter(fn (array $row): array => array_map(strtoupper(...), $row));
        $buffer->insert(['jane', 'doe']);
        self::assertSame(['JANE', 'DOE'], $buffer->nth(0));
    }

    #[Test]
    public function it_formats_the_data_before_validation(): void
    {
        $buffer = new Buffer();
        $buffer->addFormatter(fn (array $row): array => array_map(strtoupper(...), $row));
        $buffer->addValidator(fn (array $row): bool => strtolower($row[1]) === $row[1], 'func1');

        $this->expectException(CannotInsertRecord::class);
        $buffer->insert(['jane', 'doe']);
    }

    #[Test]
    public function it_makes_a_difference_between_record_offset_first_and_last(): void
    {
        $csv = <<<CSV
date,temperature,place
2011-01-01,1,Galway
2011-01-02,-1,Galway
2011-01-03,0,Galway
2011-01-01,6,Berkeley
2011-01-02,8,Berkeley
2011-01-03,5,Berkeley
CSV;

        $document = Reader::fromString($csv);
        $document->setHeaderOffset(0);

        $buffer = Buffer::from($document->slice(2, 3));
        $bufferAsArray = iterator_to_array($buffer->getRecords());
        $firstRecord = ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Galway'];
        $lastRecord = ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Berkeley'];

        self::assertSame($firstRecord, $buffer->first());
        //offset 3 based on the original CSV document
        self::assertSame($firstRecord, $bufferAsArray[$buffer->firstOffset()]);
        self::assertSame($lastRecord, $buffer->last());
        //offset 5 based on the original CSV document
        self::assertSame($lastRecord, $bufferAsArray[$buffer->lastOffset()]);

        //delete all records except the first record!
        $buffer->delete(Column::filterOn('temperature', '<>', '0'));
        self::assertSame($firstRecord, $bufferAsArray[$buffer->firstOffset()]);
        self::assertSame($firstRecord, $bufferAsArray[$buffer->lastOffset()]);
    }

    #[Test]
    public function it_implements_the_truncate_method(): void
    {
        $buffer = new Buffer(['date', 'temperature', 'place']);
        $buffer->insert(
            ['date' => '2011-01-01', 'temperature' => '1', 'place' => null],
            ['date' => '2011-01-02', 'temperature' => '-1', 'place' => 'Berkeley'],
            ['date' => '2011-01-03', 'temperature' => '0', 'place' => 'Berkeley'],
            ['date' => '2011-01-01', 'temperature' => '6', 'place' => 'Galway'],
            ['date' => '2011-01-02', 'temperature' => '8', 'place' => 'Galway'],
            ['date' => '2011-01-03', 'temperature' => '5', 'place' => 'Galway'],
        );

        self::assertFalse($buffer->isEmpty());
        self::assertTrue($buffer->hasHeader());

        $buffer->truncate();

        self::assertTrue($buffer->isEmpty());
        self::assertTrue($buffer->hasHeader());
    }
}
