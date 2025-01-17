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

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SQLite3;
use SQLite3Exception;
use SQLite3Result;
use SQLite3Stmt;
use stdClass;

use function iterator_to_array;

final class RdbmsResultTest extends TestCase
{
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
        /** @var RdbmsResult $tabularData */
        $tabularData = RdbmsResult::tryFrom($result);

        self::assertSame(['id', 'name', 'email'], $tabularData->getHeader());
        self::assertCount(6, iterator_to_array($tabularData));
        self::assertSame(
            ['id' => 1, 'name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ResultSet::createFromTabularData($tabularData)->first()
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
        /** @var RdbmsResult $tabularData */
        $tabularData = RdbmsResult::tryFrom($stmt);

        self::assertSame(['id', 'name', 'email'], $tabularData->getHeader());
        self::assertCount(6, iterator_to_array($tabularData));
        self::assertSame(
            ['id' => 1, 'name' => 'Ronnie', 'email' => 'ronnie@example.com'],
            ResultSet::createFromTabularData($tabularData)->first()
        );
    }

    #[Test]
    public function it_will_fail_with_an_unknown_object(): void
    {
        self::assertNull(RdbmsResult::tryFrom(new stdClass()));
    }
}
