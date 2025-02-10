---
layout: default
title: Tabular Data Importer
---

# Tabular Data

<p class="message-notice">Starting with version <code>9.22.0</code></p>

Since version `9.6` the package provides a common API to works with tabular data like structure. A tabular data
is data organized in rows and columns. The fact that the package aim at interacting mainly with CSV does not
restrict its usage to CSV document only, In fact if you can provide a tabular data structure to the package
it should be able to manipulate such data with ease. Hence, the introduction of the `TabularData` interface.to allow
interoperates with any tabular structure.

As seen by the package a tabular data is:

- a collection of similar records (preferably consistent in their size);
- an optional header with unique values;

This `TabularData` interface such contract by extending PHP's `IteratorAggregate` interface and by providing the
`getHeader` method which returns a list of unique string (which can be empty if no header is provided).

```php
interface TabularData extends IteratorAggregate
{
    /** @return list<string> */
    public function getHeader(): array;
}
```

## Basic Usage

Once a `TabularData` implementing object is given to the `ResultSet` class it can be manipulated and inspected as if
it was a CSV document. It will effectively access the full reading API provided by the package.

For instance the `Reader` class  implements the `TabularData` interface as such you can instantiate directly
a `ResultSet` instance using the following code:

```php
$resultSet = ResultSet::createFromTabularData(
    Reader::createFromPath('path/to/file.csv')
);
```

## Database Importer usage

A common source of tabular data are RDBMS result. From listing the content of a table to returning the result of
a complex query on multiple tables with joins, RDBMS result are always express as tabular data. As such it is possible
to convert them and manipulate via the package. To ease such manipulation the `ResultSet` class exposes the
`ResultSet::createFromTabularData` method:

```php
$connection = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $connection->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = ResultSet::createFromTabularData($stmt)->nth(23);
```

The `createFromTabularData` can be used with the following Database Extensions:

- SQLite3 (`SQLite3Result` object)
- MySQL Improved Extension (`mysqli_result` object)
- PostgreSQL (`PgSql\Result` object returned by the `pg_get_result`)
- PDO (`PDOStatement` object)
- Any class that implements the `TabularData` interface

Behind the scene the named constructor leverages the `League\Csv\RdbmsResult` class which implements the `TabularData` interface.
This class is responsible from converting RDBMS results into `TabularData` instances. But you can also use the class
as a standalone feature to quickly

- retrieve column names from the listed Database extensions as follows:

```php
$connection = pg_connect("dbname=publisher");
$result = pg_query($connection, "SELECT * FROM authors");
$result !== false || throw new RuntimeException('PostgreSQL results not available');

$names = RdbmsResult::columnNames($result);
//will return ['firstname', 'lastname', ...]
```

- convert the result into an `Iterator` using the `rows` public static method.

```php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connection = new mysqli("localhost", "my_user", "my_password", "world");
$result = $connection->query("SELECT * FROM authors");
$result instanceOf mysqli_result || throw new RuntimeException('MySQL results not available');
foreach (RdbmsResult::rows($stmt) as $record) {
  // returns each found record which match the processed query.
}
```

<p class="message-warning">The <code>PDOStatement</code> class does not support rewinding the object.
To work around this limitation, the <code>RdbmsResult</code> stores the results in a
<code>ArrayIterator</code> instance for cache which can lead to huge memory usage if the
returned <code>PDOStatement</code> result is huge.</p>

## Generic Importer Logic

Implementing the `TabularData` should be straightforward, you can easily convert any structure into a `TabularData` instance
using the following logic. Keep in mind that the codebase to generate an instance may vary depending on the source and the
size of your data but the logic should stay the same.

```php
use League\Csv\ResultSet;
use League\Csv\TabularData;

$payload = <<<JSON
[
    {"id": 1, "firstname": "Jonn", "lastname": "doe", "email": "john@example.com"},
    {"id": 2, "firstname": "Jane", "lastname": "doe", "email": "jane@example.com"},
]
JSON;

$tabularData = new class ($payload) implements TabularData {
    private readonly array $header;
    private readonly ArrayIterator $rows;
    public function __construct(string $payload)
    {
        try {
            $data = json_decode($payload, true);
            $this->header = array_keys($data[0] ?? []);
            $this->rows = new ArrayIterator($data);
        } catch (Throwable $exception) {
            throw new ValueError('The provided JSON payload could not be converted into a Tabular Data instance.', previous: $exception);
        }
    }

    public function getHeader() : array
    {
        return $this->header;
    }

    public function getIterator() : Iterator
    {
        return $this->rows;
    }
};

$resultSet = ResultSet::createFromTabularData($tabularData);
```
