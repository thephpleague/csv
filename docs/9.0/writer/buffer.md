---
layout: default
title: Tabular Data Buffer
---

# Tabular Data Buffer

<p class="message-notice">Available since version <code>9.22.0</code></p>

The `League\Csv\Buffer` class represents a unit of data processing that can be used to manage and transform tabular data
efficiently using basic CRUD operations. Because the operations are related to tabular data processing, in addition to
CRUD operations, a buffer implements the basic methods in relation to tabular data. Last but not least, the buffer
can not handle large tabular data content as all the data is stored in-memory, so it does not takes advantage of
PHP stream capabilities like the `Reader` or the `Writer` do.

## Loading Data into the buffer

The `Buffer` object can be instantiated from any object that implements the `League\Csv\TabularData` like the `Reader`
or the `ResultSet` classes:

```php
$buffer = Buffer::from(Reader::createFromPath('path/to/file.csv'));
```

Apart from `TabularData` implementing object, the method also accepts results from RDBMS query as shown below:

```php
$db = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $db->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = Buffer::from($stmt)->nth(23);
// returns ['id' => 42, 'firstname' => 'john', 'lastname' => 'doe', ...]
```

The `from` supports the following Database Extensions:

- SQLite3 (`SQLite3Result` object)
- MySQL Improved Extension (`mysqli_result` object)
- PostgreSQL (`PgSql\Result` object returned by the `pg_get_result`)
- PDO (`PDOStatement` object)

<p class="message-info">The <code>Buffer</code> class is mutable. On instantiation, it copies and stores the full source data in-memory.</p>

You can tell the `Buffer` instance to exclude the header when importing the data using the `from` named constructor
using the method second optional argument with one of the class public constant:

- `Buffer::INCLUDE_HEADER`
- `Buffer::EXCLUDE_HEADER`

```php
$db = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $db->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = Buffer::from($stmt, Buffer::EXCLUDE_HEADER)->nth(23);
//will return a list of properties without any column name attach to them!!
// returns [42, 'john', 'doe', ...]
```

### Generic Importer Logic

The `League\Csv\Buffer` class can also be used to ease importing data from various source to be handled
by the package. Keep in mind that the codebase to generate an instance may vary depending on the source and
the size of your data but the logic should stay the same.

```php
use League\Csv\Buffer;
use League\Csv\TabularData;

$payload = <<<JSON
[
    {"id": 1, "firstname": "Jonn", "lastname": "doe", "email": "john@example.com"},
    {"id": 2, "firstname": "Jane", "lastname": "doe", "email": "jane@example.com"},
]
JSON;

$data = json_decode($payload, true);
$tabularData = new Buffer(rows: $data, header: array_keys($data[0] ?? []))
```

## Modifying the buffer data

Because of its in-memory and mutable state, the `Buffer` is best suited to help modifying the data on the fly before
persisting it on a more suitable storage layer. To do so, the class provide a straightforward CRUP public API.

### Insert Records

The class provides two method to insert records `insertOne` will insert a single record while `insertAll` will insert new records into the instance.
Because tabular data can have a header or not, both methods accept either a list of values or an array of column names and
values as shown below:

```php
$buffer = Buffer::from(Reader::createFromPath('path/to/file.csv'));
$affectedRowsCount = $buffer->insertAll([['first', 'second', 'third']]);
$affectedRowsCount = $buffer->insertOne(['first', 'second', 'third']);
```

The method returns the number of successfully inserted records or trigger an exception if the parameters are invalid.

<p class="message-notice">If no header is defined for the instance, column consistency is not checked on insertion.
And only list are accepted as record to be inserted.</p>

Let's create a new `Buffer` instance from a `Reader` object.

```php
$document = Reader::createFromPath('path/to/file.csv');
$document->setHeaderOffset(0);
$buffer = Buffer::from($document); 
$buffer->getHeader(); // returns ['column1', 'column2', 'column3']
```

We can insert a new record using a list as long as the list has the same length as the `Buffer` instance or the
`Buffer` instance has no header attached to it.

```php
$affectedRowsCount = $buffer->insertOne(['first', 'second', 'third']); 
//will work because the list contains the same number of fields as in the header
```

We can also insert a record if it shares the exact same key as the header values.

```php
$affectedRowsCount = $buffer->insertAll([[
    'column1' => 'first', 
    'column2' => 'second', 
    'column3' => 'third',
]]); 
```

On the other hand, trying to insert an incomplete record will trigger an exception.

```php
$buffer->insertOne(['column1' => 'first',  'column3' => 'third']); //will trigger an exception
```

The same will happen if the list does not contain the same number of fields as the header does when it is present.

```php
$buffer->insertOne(['first', 'third']); //will trigger an exception
```

### Update or Delete Records

The class also provides an `update` and `delete` methods. Those method are responsible for updating or deleting records
based on some constraints and use the following signature.

```php
use League\Csv\Buffer;
use League\Csv\Query\Predicate;

Buffer::update(Predicate|Closure|Callable|array|int $where, array $record): int;
Buffer::delete(Predicate|Closure|Callable|array|int $where): int;
```

Just like the `insert` method, these methods return the number of successfully updated or deleted records or
trigger an exception if the parameters are invalid.

The `$where` argument can be:

An integer in which case it represents the specific offset of the `Buffer`. If the offset does not exist,
an exception is triggered.

```php
$buffer = Buffer::from(Reader::createFromPath('path/to/file.csv'));
$affectedRowsCount = $buffer->update(234, ['column1' => 'first', 'column2' => 'second']);
$buffer->delete(42); //delete the record with the offset = 42
```

A list of integer representing each a specific offset of the `Buffer`. All the offset **MUST** exist otherwise an
exception will be triggered.

```php
$buffer = Buffer::from(Reader::createFromPath('path/to/file.csv'));
$affectedRowsCount = $buffer->update([234, 5, 28], [1 => 'second']);
$buffer->delete([234, 5, 28]); //delete the record with the offset = 42
```

if the above example, the update is performed using the field offset instead of the field name. This can be handy if
the `Buffer` instance has no header, but it works with or without the presence of one.

A callable or a `League\Csv\Predicate` implementing class.

```php
use League\Csv\Buffer;
use League\Csv\Query\Constraint\Column;
use League\Csv\Reader;

$reader = Reader::createFromPath('path/to/file.csv');
$reader->setHeaderOffset(0);

$buffer = Buffer::from($reader->slice(0, 300)); //copy the first 300 lines of the Reader class
$affectedRowsCount = $buffer->update(Column::filterOn('location', '=', 'Berkeley'), ['location' => 'Galway']);
```

The previous example will update all the rows `location` field from the `Buffer` instance which contains the value `Berkeley`.
To know more about the predicates you can refer to the `ResultSet` documentation page.

### Record validation

By default, the `Buffer` instance will only validate the column field names, if a header is provided, otherwise,
column consistency or column value are ignored. To improve validation you can use a record validator.

The validator is a `callable` or a `Closure` which takes a single record as an `array` as its sole argument and returns
a `boolean` to indicate if it satisfies the validator rule.

```php
function(array $record): bool
```

The validator **must** return `true` to validate the submitted record.

Any other expression, including truthy ones like `yes`, `1` will make the inserting or updating methods throw
an `League\Csv\CannotInsertRecord` exception.

You can attach as many validators as you want using the `Buffer::addValidator` method. Validators are applied following
the *First In First Out* rule.

<p class="message-warning">The record is checked against your supplied validators <strong>after it has been checked for field names integrity</strong>.</p>

`Buffer::addValidator` takes two (2) **required** parameters:

- A validator `callable`;
- A validator name. If another validator was already registered with the given name, it will be overridden.

On failure a `League\Csv\CannotInsertRecord` exception is thrown.
This exception will give access to:

- the validator name;
- the record which failed the validation;

```php
use League\Csv\Buffer;
use League\Csv\CannotInsertRecord;

$buffer = new Buffer();
$buffer->addValidator(fn (array $row): bool => 10 == count($row), 'row_must_contain_10_cells');

try {
    $buffer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (CannotInsertRecord $e) {
    echo $e->getName(); //displays 'row_must_contain_10_cells'
    $e->getData();//returns the invalid data ['john', 'doe', 'john.doe@example.com']
}
```

## Persisting Buffer data

The `Buffer` content can be store using the `to` method. The method takes 2 arguments, the `Writer` class or any class
that implements the `TabularWriter` interface and the same  second optional argument used with the `from` method to tell
whether the header should also be written as the first line in the stored persistence layer using the
`TabularWriter` or not.

```php
use League\Csv\Buffer;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/input.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30000)));

// apply some CRUD operation or not depending
// on your business logic

$writer = Writer::createFromPath('/path/to/output.csv');
$buffer->to($writer, Buffer::EXCLUDE_HEADER);
```

If the header is present it will be the first entry to be written if you need to write the header on another
line you should manually store the `Buffer` instance using your own code as shown bellow:

```php
use League\Csv\Buffer;
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/input.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30_000)));

// apply some CRUD operation or not depending
// on your business logic

$writer = Writer::createFromPath('/path/to/output.csv');
$writer->insertAll($buffer->getRecords());
$writer->insertOne($buffer->getHeader());
```

Of course, you can use any [converter class](/9.0/converter/) that can convert the data into
a `HTML`, a `XML` or a `Json` document.

```php
use League\Csv\Buffer;
use League\Csv\JsonConverter;
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/input.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30_000)));

// apply some CRUD operation or not depending
// on your business logic

(new JsonConverter())
    ->withPrettyPrint(2)
    ->withUnescapedSlashes()
    ->withoutUnescapedUnicode()
    ->save($buffer->getRecords(), '/path/to/output.json')
```

Or simply, use the class select features to expose the buffer content to your specific storing logic in your codebase;

## Accessing Buffer data

Since version `9.6` the package provides a common API to works with tabular data like structure. A tabular data
is data organized in rows and columns. The fact that the package aim at interacting mainly with CSV does not
restrict its usage to CSV document only, In fact if you can provide a tabular data structure to the package
it should be able to manipulate such data with ease. Hence, the introduction of the `TabularData` interface to improve
interoperability with any tabular structure.

As seen by the package a tabular data is:

- a collection of similar records (preferably consistent in their size);
- an optional header with unique values;

The `TabularData` interface provides basic operations to fulfill the above requirements.

```php
interface TabularData
{
    /** @return list<string> */
    public function getHeader(): array;
    public function getRecords(array $header = []): Iterator<int, array>
    public function getRecordsAsObject(string $className, array $header = []): Iterator<int, object>
    public function nth(int $offset): array
    public function nthAsObject(int $offset, string $className, array $header = []): ?object
    public function fetchColumn(int|string $offset): Iterator;
    public function fetchPairs(int|string $offset_index, int|string $value_index): Iterator;
    public function recordCount(): int;
}
```

The `Buffer` class implements the `TabularData` interface, if you already are familiar with the `Reader` class
then you will be familiar with the API. You may refer to the [tabular data reader API](/9.0/reader/tabular-data-reader/)
documentation to see how these methods work.

If you need more advanced filtering capabilities you can use the [Statement](/9.0/reader/statement/) class for that.

```php
use League\Csv\Buffer;
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30000)));

// apply some CRUD operation or not depending
// on your business logic

$curDate = new DateTimeImmutable();
$records = new Statement()
    ->andWhere(1, '=', '10') //filtering is done of the second column
    ->orWhere('birthdate', fn (string $value): bool => DateTimeImmutable::createFromFormat('Y-m-d', $value) < $curDate) //filtering is done on the `birthdate` column
    ->whereNot('firstname', 'starts_with', 'P') //filtering is done case-sensitively on the first character of the column value
    ->process($buffer);
```

`$records` will be a `ResultSet` instance that you can manipulate further more if needed.

Last but not least, since the `Buffer` is an in-memory tabular data it exposes the following 2 (two) methods `Buffer::isEmpty`
and `Buffer::includeHeader` to quickly know if the instance contains a defined header and if it has already some records in it.

```php
use League\Csv\Buffer;
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30000)));

$buffer->isEmpty();       // return false
$buffer->includeHeader(); // return true

$emptyBuffer = new Buffer();
$emptyBuffer->isEmpty();       // return true
$emptyBuffer->includeHeader(); // return false
```
