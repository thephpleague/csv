---
layout: default
title: CSV document Reader connection
---

# Reader Connection

The `League\Csv\Reader` class extends the general connections [capabilities](/9.0/connections/) to ease selecting and manipulating CSV document records.

<p class="message-notice">Starting with version <code>9.1.0</code>, <code>createFromPath</code> will have its default set to <code>r</code>.</p>
<p class="message-notice">Prior to <code>9.1.0</code>, by default, the mode for a <code>Reader::createFromPath</code> is <code>r+</code> which looks for write permissions on the file and throws an <code>Exception</code> if the file cannot be opened with the permission set. For sake of clarity, it is strongly suggested to set <code>r</code> mode on the file to ensure it can be opened.</p>
<p class="message-info">Starting with version <code>9.6.0</code>, the class implements the <code>League\Csv\TabularDataReader</code> interface.</p>
<p class="message-info">Starting with version <code>9.8.0</code>, the class implements the <code>::fetchColumnByName</code> and <code>::fetchColumnByOffset</code> methods.</p>
<p class="message-info">Starting with version <code>9.9.0</code>, the class implements the <code>::first</code> and <code>::nth</code> methods.</p>

## CSV example

Many examples in this reference require a CSV file. We will use the following file `file.csv` containing the following data:

```csv
"First Name","Last Name",E-mail
john,doe,john.doe@example.com
jane,doe,jane.doe@example.com
john,john,john.john@example.com
jane,jane
```

## CSV header

You can set and retrieve the header offset as well as its corresponding record.

### Description

```php
public Reader::setHeaderOffset(?int $offset): self
public Reader::getHeaderOffset(void): ?int
public Reader::getHeader(void): array
```

### Example

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
```

If no header offset is set:

- `Reader::getHeader` method will return an empty array.
- `Reader::getHeaderOffset` will return `null`.

<p class="message-info">By default no header offset is set.</p>

<p class="message-warning">Because the header is lazy loaded, if you provide a positive offset for an invalid record a <code>SyntaxError</code> exception will be triggered when trying to access the invalid record.</p>

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
$header_offset = $csv->getHeaderOffset(); //returns 1000
$header = $csv->getHeader(); //throws a SyntaxError exception
```

Because the csv document is treated as tabular data the header can not contain duplicate entries.
If the header contains duplicates an exception will be thrown on usage.

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->fetchOne(0); //returns ['field1', 'field2', 'field1', 'field4']
$csv->setHeaderOffset(0); //valid offset but the record contain duplicates
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //throws a SyntaxError exception
```

<p class="message-info">Starting with <code>9.7.0</code> the <code>SyntaxError</code> exception thrown will return the list of duplicate column names.</p>

```php
use League\Csv\Reader;
use League\Csv\SyntaxError;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->fetchOne(0); //returns ['field1', 'field2', 'field1', 'field4']
$csv->setHeaderOffset(0); //valid offset but the record contain duplicates
$header_offset = $csv->getHeaderOffset(); //returns 0
try {
    $header = $csv->getHeader(); //throws a SyntaxError exception
} catch (SyntaxError $exception) {
    $duplicates = $exception->duplicateColumnNames(); //returns ['field1']
}
```

## CSV records

```php
public Reader::getRecords(array $header = []): Iterator
```

### Reader::getRecords basic usage

The `Reader` class lets you access all its records using the `Reader::getRecords` method.
The method returns an `Iterator` containing all CSV document records. It will extract the records using the [CSV controls characters](/9.0/connections/controls/);

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'john',
    //  'doe',
    //  'john.doe@example.com'
    // );
}
```

### Reader::getRecords with Reader::setHeaderOffset

If you specify the CSV header offset using `setHeaderOffset`, the found record will be combined to each CSV record to return an associative array whose keys are composed of the header values.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'First Name' => 'jane',
    //  'Last Name' => 'doe',
    //  'E-mail' => 'jane.doe@example.com'
    // );
}
```

### Reader::getRecords with its optional argument

Conversely, you can submit your own header record using the optional `$header` argument of the `getRecords` method.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstname' => 'jane',
    //  'lastname' => 'doe',
    //  'email' => 'jane.doe@example.com'
    // );
}
```

<p class="message-notice">The optional <code>$header</code> argument from the <code>Reader::getRecords</code> takes precedence over the header offset property but its corresponding record will still be removed from the returned <code>Iterator</code>.</p>

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstname' => 'jane',
    //  'lastname' => 'doe',
    //  'email' => 'jane.doe@example.com'
    // );
}
//the first record will still be skipped!!
```

<p class="message-warning">In both cases, if the header record contains non-unique string values, a <code>Exception</code> exception is triggered.</p>

### Using the IteratorAggregate interface

Because the `Reader` class implements the `IteratorAggregate` interface you can directly iterate over each record using the `foreach` construct and an instantiated `Reader` object.
You will get the same results as if you had called `Reader::getRecords` without its optional argument.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
foreach ($reader as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'First Name' => 'john',
    //  'Last Name' => 'doe',
    //  'E-mail' => john.doe@example.com'
    // );
}
```

## Records normalization

The returned records are normalized using the following rules:

- [Stream filters](/9.0/connections/filters/) are applied if present.
- Empty records are skipped if present.
- The document BOM sequence is skipped if present.
- If a header record was provided, the number of fields is normalized to the number of fields contained in that record:
  - Extra fields are truncated.
  - Missing fields are added with a `null` value.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'First Name' => 'jane',
    //  'Last Name' => 'jane',
    //  'E-mail' => null
    // );
}
```

### Controlling the presence of empty records

<p class="message-info">New since version <code>9.4.0</code></p>

By default, the CSV document normalization removes empty records, but you can control the presence of such records using the following methods:

```php
Reader::skipEmptyRecords(): self;
Reader::includeEmptyRecords(): self;
Reader::isEmptyRecordsIncluded(): bool;
```

- Calling `Reader::includeEmptyRecords` will ensure empty records are left in the `Iterator` returned by `Reader::getRecords`,
conversely `Reader::skipEmptyRecords` will ensure empty records are skipped.
- At any given time you can ask your Reader instance if empty records will be stripped or included using the `Reader::isEmptyRecordsIncluded` method.
- If no header offset is specified, the empty record will be represented by an empty `array`. Conversely, for consistency, an empty record will be represented by an array filled with `null` values as expected from header presence normalization.

<p class="message-notice">The record offset is always independent of the presence of empty records.</p>

```php
use League\Csv\Reader;

$source = <<<EOF
"parent name","child name","title"


"parentA","childA","titleA"
EOF;

$reader = Reader::createFromString($source);
$reader->isEmptyRecordsIncluded(); //returns false
iterator_to_array($reader, true);
// [
//     0 => ['parent name', 'child name', 'title'],
//     3 => ['parentA', 'childA', 'titleA'],
// ];

$reader->includeEmptyRecords();
$reader->isEmptyRecordsIncluded(); //returns true
iterator_to_array($reader, true);
// [
//     0 => ['parent name', 'child name', 'title'],
//     1 => [],
//     2 => [],
//     3 => ['parentA', 'childA', 'titleA'],
// ];

$reader->setHeaderOffset(0);
iterator_to_array($reader, true);
// [
//     1 => ['parent name' => null, 'child name' => null, 'title' => null],
//     2 => ['parent name' => null, 'child name' => null, 'title' => null],
//     3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
// ];

$reader->skipEmptyRecords();
$reader->isEmptyRecordsIncluded(); //returns false
$res = iterator_to_array($reader, true);
// [
//     3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
// ];
```

## Records count

You can retrieve the number of records contained in a CSV document using PHP's `count` function because the `Reader` class implements the `Countable` interface.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
count($reader); //returns 4
```

If a header offset is specified, the number of records will not take into account the header record.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
count($reader); //returns 3
```

<p class="message-info">New since version <code>9.4.0</code></p>

If empty records are to be preserved, the number of records will be affected.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file-with-two-empty-records.csv', 'r');
$reader->isEmptyRecordsIncluded(); //returns false
count($reader); //returns 2

$reader->includeEmptyRecords();
$reader->isEmptyRecordsIncluded(); //returns true
count($reader); //returns 4
```

<p class="message-notice">The <code>Countable</code> interface is implemented using PHP's <code>iterator_count</code> on the <code>Reader::getRecords</code> method.</p>

## Records selection

### Simple Usage

```php
public Reader::fetchColumnByName(string $columnName): Iterator
public Reader::fetchColumnByIndex(int $columnIndex = 0): Iterator
public Reader::fetchColumn(string|int $columnIndex = 0): Generator
public Reader::first(): array
public Reader::nth(int $nth_record): array
public Reader::fetchOne(int $nth_record = 0): array
public Reader::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
```

Using method overloading, you can directly access all retrieving methods attached to the [ResultSet](/9.0/reader/resultset/#records) object.

#### Example

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');

$records = $reader->fetchColumnByOffset(2);
//$records is a Generator representing all the fields of the CSV 3rd column
```

### Advanced Usage

If you require a more advance record selection, you should use a [Statement](/9.0/reader/statement/) object to process the `Reader` object. The found records are returned as a [ResultSet](/9.0/reader/resultset) object.

#### Example

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$stmt = (new Statement())
    ->offset(3)
    ->limit(5)
;

$records = $stmt->process($reader);
//$records is a League\Csv\ResultSet object
```

## Collection methods

<p class="message-notice">New methods added in version <code>9.11</code>.</p>

To ease working with the loaded CSV document the following methods derived from collection are added.
Some are just wrapper methods around the `Statement` class while others use the iterable nature
of the CSV document.

### Reader::each

Iterates over the records in the CSV document and passes each item to a closure:

```php
use League\Csv\Reader;
use League\Csv\Writer;

$writer = Writer::createFromString('');
$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->each(function (array $record, int $offset) use ($writer) {
     if ($offset < 10) {
        return $writer->insertOne($record);
     }
     
     return false;
});

//$writer will contain at most 10 lines coming from the $reader document.
// the iteration stopped when the closure return false.
```

### Reader::exists

Tests for the existence of an element that satisfies the given predicate.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$exists = $reader->exists(fn (array $records) => in_array('twenty-five', $records, true));

//$exists returns true if at cell one cell contains the word `twenty-five` otherwise returns false,
```

### Reader::reduce

Applies iteratively the given function to each element in the collection, so as to reduce the collection to
a single value.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$nbTotalCells = $reader->recude(fn (?int $carry, array $records) => ($carry ?? 0) + count($records));

//$records contains the total number of celle contains in the CSV documents.
```

### Reader::filter

Returns all the elements of this collection for which your callback function returns `true`. The order and keys of the elements are preserved.

<p class="message-info"> Wraps the functionality of <code>Statement::where</code>.</p>

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->filter(fn (array $record): => 5 === count($record));

//$recors is a ResultSet object with only records with 5 elements
```

### Reader::slice

Extracts a slice of $length elements starting at position $offset from the Collection.
If $length is `-1` it returns all elements from `$offset` to the end of the
Collection. Keys have to be preserved by this method. Calling this
method will only return the selected slice and NOT change the
elements contained in the collection slice is called on.

<p class="message-info"> Wraps the functionality of <code>Statement::offset</code>
and <code>Statement::limit</code>.</p>

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->slice(10, 25);

//$records contains up to 25 rows starting at the offest 10 (the eleventh rows)
```

### Reader::sorted

Sorts the CSV document while keeping the original keys.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->sorted(fn (array $recordA, array $recordB) => $recordA['firstname'] <=> $recordB['firstname']);

//$records is a ResultSet containing the sorted CSV document. 
//The original $reader is not changed
```

<p class="message-info"> Wraps the functionality of <code>Statement::orderBy</code>.</p>

## Records conversion

### Json serialization

The `Reader` class implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object. The interface is implemented using PHP's `iterator_array` on the `Reader::getRecords` method. As such, the returned `JSON` string data depends on the presence or absence of a header.

```php
use League\Csv\Reader;

$records = [
    ['firstname', 'lastname', 'e-mail', 'phone'],
    ['john', 'doe', 'john.doe@example.com', '0123456789'],
];

$tmp = new SplTempFileObject();
foreach ($records as $record) {
    $tmp->fputcsv($record);
}

$reader = Reader::createFromFileObject($tmp);
echo '<pre>', PHP_EOL;
echo json_encode($reader, JSON_PRETTY_PRINT), PHP_EOL;
//display
//[
//    [
//        "firstname",
//        "lastname",
//        "e-mail",
//        "phone"
//    ],
//    [
//        "john",
//        "doe",
//        "john.doe@example.com",
//        "0123456789"
//    ]
//]

$reader->setHeaderOffset(0);
echo '<pre>', PHP_EOL;
echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
//display
//[
//    {
//        "firstname": "john",
//        "lastname": "doe",
//        "e-mail": "john.doe@example.com",
//        "phone": "0123456789"
//    }
//]
```

<p class="message-notice">The record offset <strong>is not preserved on conversion</strong></p>

<p class="message-notice">To convert your CSV to <code>JSON</code> you must be sure its content is <code>UTF-8</code> encoded, using, for instance, the library <a href="/9.0/converter/charset/">CharsetConverter</a> stream filter.</p>

### Other conversions

If you wish to convert your CSV document in `XML` or `HTML` please refer to the [converters](/9.0/converter/) bundled with this library.
