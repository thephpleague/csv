---
layout: default
title: Tabular Data Reader
---

# Tabular Data Reader Common API

Introduced in version `9.6` the `League\Csv\TabularDataReader` interface provides a common
API to works with tabular data like structure. Once implemented, it can be used to work
with HTML tables, simple RDBMS tables, CSV document and so forth. A tabular data
is made of:

- a collection of similar records (preferably consistent in their size);
- an optional header with unique values;

A `record` **MUST*** be a simple PHP `array` (the `array` **MUST NOT** be nested) which can be

- a list;
- or an associative array;

A `header` is a `record` which **MUST** contain unique `string` values.

A good example of what you can achieve can be seen with the following snippet

```php
use League\Csv\Reader;

$records = Reader::createFromPath('/path/to/file.csv')
    ->filter(fn (array $record): bool => false !== filter_var($record[2] ?? '', FILTER_VALIDATE_EMAIL))
    ->select(1, 4, 5)
    ->slice(3, 5)
    ->each(function (array $record) {
        //do something meaningful with the found records
    });
```

Once you created a `TabularDataReader` implementing instance, here we are using the `Reader` you will
be able to filter, slice and select part of your data to finally access it using the `getRecords` method.
You will also be able to process the instance using a [Statement](/9.0/reader/statement/) object.
All these methods are part of the `TabularDataReader` contract. In general, `TabularDataReader` are immutable,
meaning every `TabularDataReader` method returns an entirely new `TabularDataReader` instance
leaving your source data unchanged.

## Available methods

While the `TabularDataReader` is not a fully fledged collection instance it still exposes a lots of methods
that fall into the category of records collection manipulations. Because chaining is at the core of most of
its methods you can be sure that each manipulation returns a new instance preserving your original data.

### Countable, IteratorAggregate

Any `TabularDataReader` instance implements the `Countable` and the `IteratorAggregate` interface.
It means that at any given time you can access the number of elements that are included in the instance
as well as iterate over all the record using the `foreach` structure.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
count($reader); //returns 4
foreach ($reader as $offset => $record) {
    //iterates over the 4 records.
}
```

## Selecting records

### getHeader

The `getHeader` returns the header associated with the current object. If the current object
has no header, it will return the empty array.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->getHeader(); //is empty because no header information was given
```

### getRecords

<p class="message-notice">Added in version <code>9.6.0</code> for <code>ResultSet</code>.</p>
<p class="message-notice">full mapper usage was completed in version <code>9.12</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

The `getRecords` enables iterating over all records from the current object. If the optional `$header`
argument is given, it will be used as a mapper on the record and will update the record header
and the value position.

```php
use League\Csv\Reader;

$csv = <<<CSV
Abel,14,M,2004
Abiga,6,F,2004
Aboubacar,8,M,2004
Aboubakar,6,M,2004
CSV;

$reader = Reader::createFromString($csv);
$resultSet = Statement::create()->process($reader);
$records = $resultSet->getRecords([3 => 'Year', 0 => 'Firstname', 4 => 'Yolo']);
var_dump([...$records][0]);
//returns something like this
// array:4 [
//     "Year" => "2004",
//     "Firstname" => "Abel",
//     "Yolo" => null,
//  ]
```

The argument now links the records column offset to a specific column name. In other words this means
that the array key which **MUST** be a positive integer or `0` will correspond to the CSV column offset
and its value will represent its header value.

This means that you can re-arrange the column order as well as removing or adding column to the
returned iterator. Added column will only contain the `null` value.

<p class="message-warning">If the header record contains non-unique string values, a <code>Exception</code> exception is triggered.</p>

### getRecordsAsObject

`getObjects` is deprecated in favor of `getRecordsAsObject` to make the public API more consistent.

<p class="message-notice"><code>getObjects</code> Added in version <code>9.12.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>
<p class="message-notice"><code>getRecordsAsObject</code> Added in version <code>9.15.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

If you prefer working with objects instead of arrays it is possible to deserialize your CSV document using
the `getRecordsAsObject` method. This method will convert each CSV record into your specified class instances.

```php
$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getRecordsAsObject(ClimaticRecord::class) as $instance) {
    // each $instance entry will be an instance of the Weather class;
}
```

The `getRecordsAsObject` method can take an optional `$header` argument which is the same mapper argument as the one use
with the `getRecords` method.

<p class="message-info">You can get more info on how to configure your class to enable this feature by
visiting the <a href="/9.0/reader/record-mapping">record mapping documentation</a> page</p>

### value, first,  nth, firstAsObject and nthAsObject

<p class="message-notice"><code>firstAsObject and nthAsObject</code> were added in version <code>9.14.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>
<p class="message-notice"><code>first and nth</code> were added in version <code>9.9.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>
<p class="message-notice"><code>value</code> was added in version <code>9.12.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

You may access any record using its offset starting at `0` in the collection using the `nth` method.
if no record is found, an empty `array` is returned.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$stmt = Statement::create()
    ->offset(10)
    ->limit(12)
;
$result = $stmt->process($reader);
$result->nth(3);
// access the 4th record from the recordset (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//

$result->first();
$result->nth(0);
//returns the first matching record from the recordset or an empty record if none is found.
```

As an alias to `nth`, the `first` method returns the first record from the instance without the need of an argument.

If you are only interested in retrieving a specific value from a single row, you can use
the `value` method. By default, it will return the first record item, but you are free
to specify a specific column using the column name if the header is set and/or the
column offset, If no column is found `null` is returned.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$stmt = Statement::create()
    ->offset(10)
    ->limit(12)
;
$result = $stmt->process($reader);
$result->value(2);       //returns 'john.doe@example.com'
$result->value('email'); //returns 'john.doe@example.com'
$result->value('toto'); //returns null
$result->value(42); //returns null
```

<p class="message-warning">The <code>fetchOne</code> method was deprecated in version <code>9.9.0</code>.
it is recommended to use the <code>nth</code> method instead.</p>

`firstAsObject` and `nthAsObject` are counterpart of `first` and `nth` respectively but returns `null` or
an instance of the specified object. The methods requires, in addition to the record
offset, the class name and optionally a header mapper just like `getRecordsAsObjects`.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$csv = Reader::createFromPath('/path/to/my/file.csv', 'r');
$csv->setHeaderOffset(0);

$csv->nthAsObject(3, ClimaticRecord::class);
$csv->firstAsObject(OtherClimatRecord::class, [2 => 'observedOn', 1 => 'temparature', 0 => 'place']);
```

### exists

<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

Tests for the existence of a record that satisfies a given predicate.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$exists = $resultSet->exists(fn (array $records) => in_array('twenty-five', $records, true));

//$exists returns true if at least one cell contains the word `twenty-five` otherwise returns false,
```

## Selecting columns

### fetchColumnByName

The `fetchColumnByName` returns an Iterator containing all the values of a single column specified by its header name if it exists.

```php
$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = Statement::create()->process($reader);
foreach ($records->fetchColumnByName('e-mail') as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
```

<p class="message-notice">Added in version <code>9.8.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### fetchColumnByOffset

The `fetchColumnByOffset` returns an Iterator containing all the values of a single column specified by its
header offset.

```php
$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = Statement::create()->process($reader);
foreach ($records->fetchColumnByOffset(3) as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
```

<p class="message-notice">Added in version <code>9.8.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### fetchPairs

The `fetchPairs` method returns an Iterator of key-value pairs from two tabular data columns. The method
expect 2 arguments, both can be:

- an integer which represents the column name index;
- a string representing the value of a column name;

These arguments behave exactly like the `$columnIndex` from `ResultSet::fetchColumnByName`
and `ResultSet::fetchColumnByOffset`.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$str = <<<EOF
john,doe
jane,doe
foo,bar
sacha
EOF;

$reader = Reader::createFromString($str);
$records = Statement::create()->process($reader);

foreach ($records->fetchPairs() as $firstname => $lastname) {
    // - first iteration
    // $firstname -> 'john'
    // $lastname  -> 'doe'
    // - second iteration
    // $firstname -> 'jane'
    // $lastname  -> 'doe'
    // - third iteration
    // $firstname -> 'foo'
    // $lastname  -> 'bar'
    // - fourth iteration
    // $firstname -> 'sacha'
    // $lastname  -> null
}
```

### Notes

- If no `$offsetIndex` is provided it defaults to `0`;
- If no `$valueIndex` is provided it defaults to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;

<p class="message-warning">If the <code>TabularDataReader</code> contains column names and the submitted arguments are not found, an <code>Exception</code> exception is thrown.</p>

## Functional methods

### each

<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

The `each` method iterates over the records in the tabular data collection and passes each record to a
closure.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$writer = Writer::createFromString();
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

You may interrupt the iteration if the closure passed to `each` returns `false`.

### reduce

<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

The `reduce` method reduces the tabular data structure to a single value, passing
the result of each iteration into the subsequent iteration:

```php
use League\Csv\Reader;
use League\Csv\ResultSet;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = ResultSet::createFromTabularDataReader($reader);

$nbTotalCells = $resultSet->reduce(fn (?int $carry, array $records) => ($carry ?? 0) + count($records));

//$records contains the total number of cells contains in the $resultSet
```

The closure is similar as the one used with `array_reduce`.

## Collection methods

The following methods return all a new `TabularDataReader` instance.
They effectively allow selecting a range of records or columns contained
within the `TabularDataReader` schema.

### filter

Returns all the elements of this collection for which your callback function returns `true`. The order and
keys of the elements are preserved.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->filter(fn (array $record): => 5 === count($record));

//$records is a ResultSet object with only records with 5 elements
```

<p class="message-info"> Wraps the functionality of <code>Statement::where</code>.</p>
<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### sorted

Sorts the CSV document while keeping the original keys.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = $reader->sorted(fn (array $recordA, array $recordB) => $recordA['firstname'] <=> $recordB['firstname']);

//$records is a ResultSet containing the sorted CSV document. 
//The original $reader is not changed
```

<p class="message-info"> Wraps the functionality of <code>Statement::orderBy</code>.</p>
<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### slice

Extracts a slice of $length elements starting at position $offset from the Collection. If $length is `-1` it returns all elements from `$offset` to the end of the Collection.
Keys have to be preserved by this method. Calling this method will only return the selected slice and NOT change the elements contained in the collection slice is called on.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$records = $resultSet->slice(10, 25);

//$records is a TabularDataReader which contains up to 25 rows
//starting at the offset 10 (the eleventh rows)
```

<p class="message-info"> Wraps the functionality of <code>Statement::offset</code> and <code>Statement::limit</code>.</p>
<p class="message-notice">Added in version <code>9.11.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### select

You may not always want to select all columns from the tabular data. Using the `select` method,
you can specify which columns to use. The column can be specified by their name, if the instance
`getHeader` returns a non-empty array, or you can default to using the column offset. You
can even mix them both.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv')
    ->select(2, 5, 8);

//$reader is a new TabularDataReader with 3 columns
```

<p class="message-notice">Added in version <code>9.12.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>
<p class="message-info"> Wraps the functionality of <code>Statement::select</code>.</p>

### mapHeader

<p class="message-notice">Added in version <code>9.15.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>
<p class="message-info"> Wraps the functionality of <code>Statement::process</code>.</p>

Complementary to the `select` method, the `mapHeader` method allows you to redefine the column header
names and order and returns a new `TabularDataReader`. The submitted array argument act like the
array from the `Statement::process` method but instead of returning a iterable structure of
records it returns a new `TabularDataReader` with a new header.

```php
$tabularData = $reader
    ->slice(0, 10)
    ->mapHeader([
        3 => 'Year',
        2 => 'Gender',
        0 => 'Firstname',
        1 => 'Count',
    ]);

//is equivalent to

$tabularData = Statement::create()
    ->offset(0)
    ->limit(10)
    ->process($reader, [
        3 => 'Year',
        2 => 'Gender',
        0 => 'Firstname',
        1 => 'Count',
    ]);
$tabularData->getHeader(); // returns ['Year', 'Gender', 'Firstname', 'Count'];
```

### matching, matchingFirst, matchingFirstOrFail

The `matching` method allows selecting records, columns or cells from the tabular data reader that match the
[RFC7111](https://www.rfc-editor.org/rfc/rfc7111) expression and returns a new collection containing these
elements without preserving the keys. The method wraps the functionality of `FragmentFinder::findAll`.
Conversely, `matchingFirst` wraps the functionality of `FragmentFinder::findFirst` and last but not least,
`FragmentFinder::findFirstOrFail` behaviour is wrap inside the `matchingFirstOrFail` method.

```php
use League\Csv\Reader;

$reader = Reader::createFromString($csv);

$reader->matching('row=3-1;4-6'); //returns an iterable containing all the TabularDataReader instance that are valid.
$reader->matchingFirst('row=3-1;4-6'); // will return 1 selected fragment as a TabularReaderData instance
$reader->matchingFirstOrFail('row=3-1;4-6'); // will throw
```

<p class="message-info"> Wraps the functionality of <code>FragmentFinder</code> class.</p>
<p class="message-notice">Added in version <code>9.12.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

### chunkBy

<p class="message-notice">Added in version <code>9.15.0</code> for <code>Reader</code> and <code>ResultSet</code>.</p>

If you are dealing with a large CSV and you want it to be split in smaller sizes for better handling you can use
the `chunkBy` method which breaks the `TabularDataReader` into multiple, smaller instances with a given size. The
last instance may contain fewer records because of the chunk size you have chosen.

```php
use League\Csv\Reader;
use League\Csv\TabularDataReader;
use League\Csv\Writer;

$chunks = Reader::createFromPath('path/to/a/huge/file.csv')->chunkBy(1000);
foreach ($chunks as $chunk) {
 // $chunk is a small CSV of 1000 records or less
}
```
