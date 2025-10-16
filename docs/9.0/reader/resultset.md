---
layout: default
title: Accessing Records from a CSV document
---

# Result Set

A `League\Csv\ResultSet` object represents the associated result set of processing a [CSV document](/9.0/reader/) with a [constraint builder](/9.0/reader/statement/).
This object is returned from [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

<p class="message-info">Starting with version <code>9.6.0</code>, the class implements the <code>League\Csv\TabularDataReader</code> interface.</p>
<p class="message-info">Starting with version <code>9.22.0</code>, the class implements the <code>League\Csv\TabularData</code> interface.</p>

## Instantiation

<p class="message-notice">Starting with version <code>9.22.0</code></p>

The `ResultSet` object can be instantiated from other objects than `Statement`.

You can instantiate it directly from any object that implements the `League\Csv\TabularData` like the `Reader` class:

```php
$resultSet = ResultSet::from(Reader::from('path/to/file.csv'));
```

Apart from `TabularData` implementing object, the method also accepts results from RDBMS query as shown below:

```php
$db = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $db->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = ResultSet::from($stmt)->nth(23);
```

The `createFromTabularData` supports the following Database Extensions:

- SQLite3 (`SQLite3Result` object)
- MySQL Improved Extension (`mysqli_result` object)
- PostgreSQL (`PgSql\Result` object returned by the `pg_get_result`)
- PDO (`PDOStatement` object)

<p class="message-warning">Beware when using the <code>PDOStatement</code>, the class does not support rewinding the object.
As such using the instance on huge results will trigger high memory usage as all the data will be stored in a
<code>ArrayIterator</code> instance for cache to allow rewinding and inspecting the tabular data.</p>

## Selecting records

Please header over the [TabularDataReader documentation page](/9.0/reader/tabular-data-reader)
for more information on the class features. If you require a more advance record selection, you
should use a [Statement or a FragmentFinder](/9.0/reader/statement/) class to process the `Reader` object. The
found records are returned as a [ResultSet](/9.0/reader/resultset) object.

## Conversions

### Json serialization

<p class="message-info">A dedicated <code>JsonConverter</code> class is added in version <code>9.17.0</code>
to help <a href="/9.0/converter/json/">converting ResultSet into proper JSON document</a> without consuming
too much memory. It is the recommended way to convert to JSON.</p>

The `ResultSet` class implements the `JsonSerializable` interface. As such you can use the `json_encode`
function directly on the instantiated object. The interface is implemented  using PHP's `iterator_array`
on the `ResultSet::getRecords` method. As such, the returned `JSON` string data is affected by the
presence or absence of column names.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$records = [
    ['firstname', 'lastname', 'e-mail', 'phone'],
    ['john', 'doe', 'john.doe@example.com', '0123456789'],
    ['jane', 'doe', 'jane.doe@example.com', '0123456789'],
];

$tmp = new SplTempFileObject();
foreach ($records as $record) {
    $tmp->fputcsv($record);
}

$reader = Reader::from($tmp)->setHeaderOffset(0);
$stmt = (new Statement())->offset(1)->limit(1);
$result = $stmt->process($reader);

echo '<pre>', PHP_EOL;
echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
//display
//[
//    {
//        "firstname": "jane",
//        "lastname": "doe",
//        "e-mail": "jane.doe@example.com",
//        "phone": "0123456789"
//    }
//]
```

<p class="message-notice">The record offset <strong>is not preserved on conversion</strong></p>
<p class="message-notice">To convert your CSV records to <code>JSON</code> you must be sure its content is <code>UTF-8</code> encoded, using, for instance, the library <a href="/9.0/converter/charset/">CharsetConverter</a> stream filter.</p>

### Other conversions

If you wish to convert your CSV document in `XML` or `HTML` please refer to the [converters](/9.0/converter/) bundled with this library.
