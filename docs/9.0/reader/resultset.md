---
layout: default
title: Accessing Records from a CSV document
---

# Result Set

A `League\Csv\ResultSet` object represents the associated result set of processing a [CSV document](/9.0/reader/) with a [constraint builder](/9.0/reader/statement/). This object is returned from [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

<p class="message-info">Starting with version <code>9.6.0</code>, the class implements the <code>League\Csv\TabularDataReader</code> interface.</p>
<p class="message-info">Starting with version <code>9.8.0</code>, the class implements the <code>::fetchColumnByName</code> and <code>::fetchColumnByOffset</code> methods.</p>

## Information

### Accessing the result set column names

```php
public ResultSet::getHeader(): array
```

`ResultSet::getHeader` returns the header associated with the current object.

#### Example: no header information was given

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
$records->getHeader(); //is empty because no header information was given
```

#### Example: header information given by the Reader object

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = Statement::create()->process($reader);
$records->getHeader(); //returns ['First Name', 'Last Name', 'E-mail'];
```

#### Example: header information given by the Statement object

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = Statement::create()->process($reader, ['Prénom', 'Nom', 'E-mail']);
$records->getHeader(); //returns ['Prénom', 'Nom', 'E-mail'];
```

### Accessing the number of records in the result set

The `ResultSet` class implements the `Countable` interface.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
count($records); //return the total number of records found
```

## Records

### Description

```php
public ResultSet::getRecords(array $header = []): Iterator
```

<p class="message-info">Starting with version <code>9.6.0</code>, the implemented <code>ResultSet::getRecords</code> method matches the same arguments and the same signature as the <code>Reader::getRecords</code> method.</p>

To iterate over each found record you can call the `ResultSet::getRecords` method which returns a `Generator` of all records found or directly use the `foreach` construct as the class implements the `IteratorAggregate` interface:

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);

foreach ($records->getRecords() as $record) {
    //do something here
}

foreach ($records as $record) {
    //do something here
}
```

<p class="message-notice">since <code>9.12.0</code> the optional <code>$header</code> is a full mapper</p>

The argument now links the records column offset to a specific column name. In other words this means
that the array key which MUST be a positive integer or `0` will correspond to the CSV column offset
and its value will represent its header value.

This means that you can re-arrange the column order as well as removing or adding column to the returned iterator.
Added column will only contain the `null` value.

Here's an example of the new behaviour.

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

As you can see the `Count` column is missing, the `Year` and `Firstname` columns are re-arranged but
present and the extra `Yolo` column is added with the value `null`

### Usage with the header

If the `ResultSet::getHeader` is not an empty `array` the found records keys will contain the returned values.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = Statement::create()->process($reader);
$records->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
foreach ($records as $record) {
    // $record contains the following data
    // array(
    //     'First Name' => 'john',
    //     'Last Name' => 'doe',
    //     'E-mail' => 'john.doe@example.com',
    // );
}
```

## Selecting a specific record

Since version <code>9.9.0</code>, the class implements the `::first` and `::nth` methods.
These methods replace the `::fetchOne` method which is deprecated and will be removed in the next major release.

These methods all return a single record from the `ResultSet`.

```php
public ResultSet::fetchOne(int $nth_record = 0): array
public ResultSet::first(): array
public ResultSet::nth(int $nth_record): array
```

The `$nth_record` argument represents the nth record contained in the result set starting at `0`.  
In the case of `fetchOne`, if no argument is given the method will return the first record from the result set.

In all cases, if no record is found, an empty `array` is returned.

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

$result->fetchOne(3);
$result->nth(3);
// access the 4th record from the recordset (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//

$result->fetchOne();
$result->first();
$result->nth(0);
//returns the first matching record from the recordset or an empty record if none is found.
```

<p class="message-notice"><code>nth</code> will throw an <code>ArgumentCountError</code> if no argument is given to it.</p>

## Selecting a single column

```php
public ResultSet::fetchColumnByName(string $name): Iterator
public ResultSet::fetchColumnByOffset(int $offset = 0): Iterator
public ResultSet::fetchColumn(string|int $columnIndex = 0): Iterator
```

Since version <code>9.8.0</code>, the class implements the `::fetchColumnByName` and `::fetchColumnByOffset` methods.
These methods replace the `::fetchColumn` method which is deprecated and will be removed in the next major release.

Both methods return an `Iterator` of all values in a given column from the `ResultSet` object, but they differ in their argument type:

`::fetchColumnByName` expects a string representing one of the values of `ResultSet::getHeader`

```php
$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = Statement::create()->process($reader);
foreach ($records->fetchColumnByName('E-mail') as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
```

<p class="message-warning">If the <code>ResultSet</code> contains column names and the <code>$name</code> is not found, an <code>Exception</code> exception is thrown.</p>

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = Statement::create()->process($reader);
foreach ($records->fetchColumnByName('foobar') as $record) {
    //throw an Exception exception if
    //no `foobar` column name is found
    //in $records->getHeader() result
}
```

`::fetchColumnByOffset` expects an integer representing the column index starting from `0`;

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
foreach ($records->fetchColumnByOffset(2) as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
```

<p class="message-notice">For both methods, if for a given record the column value is <code>null</code>, the record will be skipped.</p>

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
count($records); //returns 10;
count(iterator_to_array($records->fetchColumnByOffset(2), false)); //returns 5
//5 records were skipped because the column value is null
```

<p class="message-warning">The following paragraph describes the usage of the <code>::fetchColumn</code> method which is
deprecated as of <code>9.8.0</code> and wil be removed in the next major release.</p>

`ResultSet::fetchColumn` returns a `Generator` of all values in a given column from the `ResultSet` object.

The `$columnIndex` parameter can be:

- an integer representing the column index starting from `0`;
- a string representing one of the value of `ResultSet::getHeader`;

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
foreach ($records->fetchColumn(2) as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = Statement::create()->process($reader);
foreach ($records->fetchColumn('E-mail') as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
```

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = Statement::create()->process($reader);
count($records); //returns 10;
count(iterator_to_array($records->fetchColumn(2), false)); //returns 5
//5 records were skipped because the column value is null
```

<p class="message-warning">If the <code>ResultSet</code> contains column names and the <code>$columnIndex</code> is not found, an <code>Exception</code> exception is thrown.</p>

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = Statement::create()->process($reader);
foreach ($records->fetchColumn('foobar') as $record) {
    //throw an Exception exception if
    //no `foobar` column name is found
    //in $records->getHeader() result
}
```

## Selecting key-value pairs

`ResultSet::fetchPairs` method returns a `Generator` of key-value pairs.

```php
public ResultSet::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
```

Both arguments, `$offsetIndex` and `$valueIndex` can be:

- an integer which represents the column name index;
- a string representing the value of a column name;

These arguments behave exactly like the `$columnIndex` from `ResultSet::fetchColumn`.

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

<p class="message-warning">If the <code>ResultSet</code> contains column names and the submitted arguments are not found, an <code>Exception</code> exception is thrown.</p>

## Collection methods

<p class="message-notice">New methods added in version <code>9.11</code>.</p>

To ease working with the `ResultSet` the following methods derived from collection are added.
Some are just wrapper methods around the `Statement` class while others use the iterable nature
of the instance.

### ResultSet::each

Iterates over the records in the CSV document and passes each item to a closure:

```php
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\Writer;

$writer = Writer::createFromString('');
$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');

$resultSet = Statement::create()->process($reader);
$resultSet->each(function (array $record, int $offset) use ($writer) {
     if ($offset < 10) {
        return $writer->insertOne($record);
     }
     
     return false;
});

//$writer will contain at most 10 lines coming from the $resultSet.
// the iteration stopped when the closure return false.
```

### ResultSet::exists

Tests for the existence of an element that satisfies the given predicate.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$exists = $resultSet->exists(fn (array $records) => in_array('twenty-five', $records, true));

//$exists returns true if at cell one cell contains the word `twenty-five` otherwise returns false,
```

### Reader::reduce

Applies iteratively the given function to each element in the collection, so as to reduce the collection to
a single value.

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$nbTotalCells = $resultSet->recude(fn (?int $carry, array $records) => ($carry ?? 0) + count($records));

//$records contains the total number of celle contains in the $resultSet
```

### Reader::filter

Returns all the elements of this collection for which your callback function returns `true`. The order and keys of the elements are preserved.

<p class="message-info"> Wraps the functionality of <code>Statement::where</code>.</p>

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$records = $resultSet->filter(fn (array $record): => 5 === count($record));

//$recors is a ResultSet object with only records with 5 elements
```

### Reader::slice

Extracts a slice of $length elements starting at position $offset from the Collection. If $length is `-1` it returns all elements from `$offset` to the end of the Collection.
Keys have to be preserved by this method. Calling this method will only return the selected slice and NOT change the elements contained in the collection slice is called on.

<p class="message-info"> Wraps the functionality of <code>Statement::offset</code> and <code>Statement::limit</code>.</p>

```php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$records = $resultSet->slice(10, 25);

//$records contains up to 25 rows starting at the offset 10 (the eleventh rows)
```

### Reader::sorted

Sorts the CSV document while keeping the original keys.

<p class="message-info"> Wraps the functionality of <code>Statement::orderBy</code>.</p>

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$resultSet = Statement::create()->process($reader);

$records = $resultSet->sorted(fn (array $recordA, array $recordB) => $recordA['firstname'] <=> $recordB['firstname']);

//$records is a ResultSet containing the original resultSet. 
//The original ResultSet is not changed
```

## Conversions

### Json serialization

The `ResultSet` class implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object. The interface is implemented using PHP's `iterator_array` on the `ResultSet::getRecords` method. As such, the returned `JSON` string data is affected by the presence or absence of column names.

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

$reader = Reader::createFromFileObject($tmp)->setHeaderOffset(0);
$stmt = Statement::create()->offset(1)->limit(1);
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
