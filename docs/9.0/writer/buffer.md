---
layout: default
title: Tabular Data Buffer
---

# Tabular Data Buffer

<p class="message-info">Available since version <code>9.22.0</code></p>

The `League\Csv\Buffer` class represents a unit of data processing that can be used to manage and transform tabular data
efficiently using basic CRUD operations. Because the operations are related to tabular data processing, in addition to
CRUD operations, a buffer implements the basic methods in relation to tabular data.

<p class="message-warning">The buffer can not handle large tabular data content as all the data is stored in-memory,
It does not take advantage of PHP stream capabilities like the <code>Reader</code> or the <code>Writer</code> do. It
is up to the developper to limit its size.</p>

## Loading Data into the buffer

### Loading from the package read only classes

The `Buffer` object can be instantiated from any object that implements the package `TabularData` interface
like the `Reader` or the `ResultSet` classes:

```php
$buffer = Buffer::from(Reader::from('path/to/file.csv'));
//or
$document = Reader::from('path/to/file.csv');
$document->setHeaderOffset(0);       
$altBuffer = Buffer::from($document->slice(0, 30_000));
```

### Loading from RDBMS result

The `from` method also accepts results from RDBMS query as shown below:

```php
$db = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $db->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = Buffer::from($stmt)->nth(23);
// returns ['id' => 42, 'firstname' => 'john', 'lastname' => 'doe', ...]
```

The method supports the following Database Extensions:

- SQLite3 (`SQLite3Result` object)
- MySQL Improved Extension (`mysqli_result` object)
- PostgreSQL (`PgSql\Result` object returned by the `pg_get_result`)
- PDO (`PDOStatement` object)

You can tell the `Buffer` instance to include or exclude the header when importing the data using the
second optional argument of the `from` named constructor with one of the class public constant:

- `Buffer::INCLUDE_HEADER`
- `Buffer::EXCLUDE_HEADER`

```php
$db = new SQLite3( '/path/to/my/db.sqlite');
$stmt = $db->query("SELECT * FROM users");
$stmt instanceof SQLite3Result || throw new RuntimeException('SQLite3 results not available');

$user24 = Buffer::from($stmt, Buffer::EXCLUDE_HEADER)->nth(23);
//will return a list of properties without any column name attach to them!!
// returns [42, 'john', 'doe', ...]
// the header information will be lost and not header data will be present
```

### Generic Loading Logic

The `League\Csv\Buffer` class can also be used to ease importing data from various source to be handled
by the package. Keep in mind that the codebase to generate an instance may vary depending on the source
and the size of your data but the underlying logic should stay the same.

```php
$payload = <<<JSON
[
    {"id": 1, "firstname": "Jonn", "lastname": "doe", "email": "john@example.com"},
    {"id": 2, "firstname": "Jane", "lastname": "doe", "email": "jane@example.com"},
]
JSON;

$data = json_decode($payload, true);
$tabularData = new Buffer(array_keys($data[0] ?? [])); //new instance with a new header
$tabularData->insert(...$data);
```

### Buffer state

<p class="message-info">The <code>Buffer</code> class is mutable. On instantiation,
it copies and stores the full source data in-memory.</p>

Once loaded, at any given moment, the `Buffer` exposes the following methods:

- `Buffer::hasHeader` which tells whether a non-empty header is attached to the buffer
- `Buffer::isEmpty` which tells whether the instance contains some records or not.
- `Buffer::firstOffset` which returns the first **offset** in the buffer or `null` if the instance is empty
- `Buffer::lastOffset` which returns the last **offset** in the buffer or `null` if the instance is empty
- `Buffer::recordCount` which returns the total number of records currently present in the instance.

```php
use League\Csv\Buffer;
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::from('/path/to/file.csv');
$reader->setHeaderOffset(0);

$buffer = Buffer::from($reader->slice(50, 30_000)));
$buffer->isEmpty();     // returns false
$buffer->hasHeader();   // returns true
$buffer->firstOffset(); // returns 50
$buffer->lastOffset();  // returns the offset of the last inserted record
$buffer->recordCount(); // the total number of rows in the instance

$emptyBuffer = new Buffer();
$emptyBuffer->isEmpty();     // returns true
$emptyBuffer->hasHeader();   // returns false
$emptyBuffer->firstOffset(); // returns null
$emptyBuffer->lastOffset();  // returns null
$emptyBuffer->recordCount(); // returns 0
```

<p class="message-notice">The <code>Buffer</code> header can not be changed once the object
has been instantiated. To change the header you are required to create a new
<code>Buffer</code> instance.</p>

At any given time your can also return the last inserted record or an empty `array` if not record as yet
to be added to the buffer via the `Buffer::last` method. The same logic applies with the first inserted
record when using the `Buffer::first` method. Both methods have a `*AsObject` counterpart which maps
the found record to a specified object or returns `null` if no record is found.

```php
$buffer->first(); //returns ['firstname' => 'john', 'lastname' => 'doe', 'email' => 'johh.doe@example.com']
$buffer->firstAsObject(User::class);     // returns a User instance on success
$emptyBuffer->last();                    // returns []
$emptyBuffer->lastAsObject(User::class); // returns null
```

## Modifying the buffer data

Because of its in-memory and mutable state, the `Buffer` is best suited to help modifying
the data on the fly before persisting it on a more suitable storage layer. To do so, the
class provides a straightforward CRUD public API.

### Insert Records

The class provides the `insert` method to add records to the instance. Because tabular data
can have a header or not the method accepts either a variadic list of records values
or of associative arrays as shown below:

```php
$buffer = new Buffer(); 
$buffer->getHeader(); // returns []
$buffer->hasHeader(); // return false
$buffer->insert(
    ['moko', 'mibalé', 'misató'], 
    [
        'first column' => 'un', 
        'second column' => 'deux', 
        'third column' => 'trois',
    ],
    ['one', 'two', 'three'],
); // returns 3

return iterator_to_array($buffer->getRecords());
// [
//     ['moko', 'mibalé', 'misató'],
//     ['un', 'deux', 'trois'],
//     ['one', 'two', 'three'],
// ];
```

The method returns the number of successfully inserted records or trigger an exception if the
insertion can not occur.

<p class="message-notice">If no header is defined for the instance, column consistency is not
checked on insertion and associative array are inserted without their corresponding keys.</p>

Let's create a new `Buffer` instance **with a header specified**.

```php
$document = Reader::from('path/to/file.csv');
$document->setHeaderOffset(0); //the Reader header will be imported alongside its records
$buffer = Buffer::from($document); 
$buffer->getHeader(); // returns ['column1', 'column2', 'column3']
$buffer->hasHeader(); // return true
```

We can insert a new record using a list as long as the list has the same length as the `Buffer`
instance or the `Buffer` instance has no header attached to it.

```php
$affectedRowsCount = $buffer->insert(['first', 'second', 'third']);
$buffer->last(); // returns ['column1' => 'first', 'column2' => 'second', 'column3' => 'third'];
```

We can also insert a record if it shares the exact same key as the header values.

```php
$affectedRowsCount = $buffer->insert([
    'column1' => 'first', 
    'column2' => 'second', 
    'column3' => 'third',
]); 
```

On the other hand, trying to insert an incomplete record will trigger an exception.

```php
$buffer->insert(['column1' => 'first', 'column3' => 'third']); //will trigger an exception
```

The same will happen if the list does not contain the same number of fields as the header does when it is present.

```php
$buffer->insert(['first', 'third']); //will trigger an exception
```

### Update or Delete Records

The class also provides the `update`, `delete` and `truncate` methods. Those method are responsible for
updating or deleting records based on some constraints and use the following signature.

```php
use League\Csv\Buffer;
use League\Csv\Query\Predicate;

Buffer::update(Predicate|Closure|callable $where, array $record): int;
Buffer::delete(Predicate|Closure|callable $where): int;
Buffer::truncate(): void;
```

The `truncate` method remove all the records present in the `Buffer` instance leaving its
header state unchanged.

```php
$document = Reader::from('path/to/file.csv');
$document->setHeaderOffset(0); //the Reader header will be imported alongside its records
$buffer = Buffer::from($document);  
$buffer->isEmpty(); // returns false
$buffer->hasHeader(); // return true
$buffer->truncrate();
$buffer->isEmpty(); // returns true
$buffer->hasHeader(); // return true
```

On the other hand, the `update` and `delete` methods return the number of successfully updated or
deleted records or trigger an exception if the parameters are invalid.

The `$where` argument can be a callable or a `League\Csv\Predicate` implementing class. This is the same
argument used with the `Statement::where` method.

```php
use League\Csv\Buffer;
use League\Csv\Query\Constraint\Column;
use League\Csv\Reader;

$reader = Reader::from('path/to/file.csv');
$reader->setHeaderOffset(0);

$buffer = Buffer::from($reader->slice(0, 300)); //copy the first 300 lines of the Reader class
$affectedRowsCount = $buffer->update(
    Column::filterOn('location', '=', 'Berkeley'), 
    ['location' => 'Galway']
);
```

The previous example will update all the rows from the `Buffer` instance where the `location` field
is equal to the `Berkeley` string. To know more about the predicates you can refer to
the `ResultSet` documentation page.

The update or deletion can be performed using the field offset or the field name.
This can be handy if the `Buffer` instance has no header, but it works with or without the presence of one.

<p class="message-info">The values returned by the <code>Buffer</code> state methods may vary depending
on the record(s) added and/or deleted.</p>

### Record formatting

Before insertion, the record can be further formatted using a formatter. A formatter is a `callable` which accepts
a single record as an `array` on input and returns an array representing the formatted record according to its
inner rules.

```php
function(array $record): array
```

You can attach as many formatters as you want using the `Buffer::addFormatter` method.
Formatters are applied following the *First In First Out* rule.

```php
$buffer = new Buffer();
$buffer->addFormatter(fn (array $row): array => array_map('strtoupper', $row));
$buffer->insert(['john', 'doe', 'john.doe@example.com']);
$buffer->last(); //returns ['JOHN', 'DOE', 'JOHN.DOE@EXAMPLE.COM']
```

### Record validation

By default, the `Buffer` instance will only validate the column field names, if a header is provided, otherwise,
column consistency or column value are ignored. To improve validation you can use a record validator.

The validator is a `callable` or a `Closure` which takes a single record as an `array` as its sole argument
and returns a `boolean` to indicate if it satisfies the validator rule.

```php
function(array $record): bool
```

The validator **must** return `true` to validate the submitted record.

Any other expression, including truthy ones like `yes`, `1` will make the inserting or updating methods throw
an `League\Csv\CannotInsertRecord` exception.

You can attach as many validators as you want using the `Buffer::addValidator` method. Validators are applied
following the *First In First Out* rule.

<p class="message-warning">The record is checked against your supplied validators <strong>after it has been checked
for field names integrity and formatted using the optionals registered formatters.</strong></p>

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
    $buffer->insert(['john', 'doe', 'john.doe@example.com']);
} catch (CannotInsertRecord $exception) {
    echo $exception->getName(); //displays 'row_must_contain_10_cells'
    $exception->getData();//returns the invalid data ['john', 'doe', 'john.doe@example.com']
}
```

## Persisting Buffer data

The `Buffer` content can be store using the `to` method. The method takes 2 arguments, the `Writer` class or any
class that implements the `TabularWriter` interface and the same  second optional argument used with the `from`
method to tell whether the header should also be written as the first line in the stored persistence layer
using the `TabularWriter` or not.

```php
use League\Csv\Buffer;
use League\Csv\Writer;

$reader = Reader::from('/path/to/input.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30000)));

// apply some CRUD operation or not depending
// on your business logic

$writer = Writer::from('/path/to/output.csv');
$buffer->to($writer, Buffer::EXCLUDE_HEADER);
```

If the header is present it will be the first entry to be written if you need to write the header on another
line you should manually store the `Buffer` instance using your own code as shown bellow:

```php
use League\Csv\Buffer;
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::from('/path/to/input.csv');
$reader->setHeaderOffset(0);
$buffer = Buffer::from($reader->slice(0, 30_000)));

// apply some CRUD operation or not depending
// on your business logic

$writer = Writer::from('/path/to/output.csv');
$writer->insertAll($buffer->getRecords());
$writer->insertOne($buffer->getHeader());
```

Of course, you can use any [converter class](/9.0/converter/) that can convert the data into
a `HTML`, a `XML` or a `Json` document.

```php
use League\Csv\Buffer;
use League\Csv\JsonConverter;
use League\Csv\Reader;

$reader = Reader::from('/path/to/input.csv');
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
it should be able to manipulate such data with ease. Hence, the introduction of the `TabularData` interface
to improve interoperability with any tabular structure.

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
    public function map(callable $callback): Iterator<int, mixed>
    public function nth(int $nth): array
    public function nthAsObject(int $nth, string $className, array $header = []): ?object
    public function fetchColumn(int|string $columnIndex): Iterator<int, mixed>
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

$reader = Reader::from('/path/to/file.csv');
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
