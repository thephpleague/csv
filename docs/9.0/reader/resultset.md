---
layout: default
title: Accessing Records from a CSV document
---

# Result Set

~~~php
<?php

class ResultSet implements Countable, IteratorAggregate, JsonSerializable
{
    public function fetchColumn(string|int $columnIndex = 0): Generator
    public function fetchOne(int $nth_record = 0): array
    public function fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
    public function getHeader(): array
    public function getRecords(): Generator
}
~~~

A `League\Csv\ResultSet` object represents the associated result set of processing a [CSV document](/9.0/reader/) with a [constraint builder](/9.0/reader/statement/). This object is returned from [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

## Informations

### Accessing the result set column names

~~~php
<?php
public ResultSet::getHeader(): array
~~~

`ResultSet::getHeader` returns the header associated with the current object.

#### Example: no header information was given

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = (new Statement())->process($reader);
$records->getHeader(); // is empty because no header information was given
~~~

#### Example: header information given by the Reader object

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader);
$records->getHeader(); // returns ['First Name', 'Last Name', 'E-mail'];
~~~

#### Example: header information given by the Statement object

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader, ['Prénom', 'Nom', 'E-mail']);
$records->getHeader(); // returns ['Prénom', 'Nom', 'E-mail'];
~~~

### Accessing the number of records in the result set

The `ResultSet` class implements implements the `Countable` interface.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = (new Statement())->process($reader);
count($records); //return the total number of records found
~~~

## Records

### Description

~~~php
<?php

public ResultSet::getRecords(void): Iterator
~~~

To iterate over each found records you can call the `ResultSet::getRecords` method which returns a `Generator` of all records found or directly use the `foreach` construct as the class implements the `IteratorAggregate` interface;

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = (new Statement())->process($reader);

foreach ($records->getRecords() as $record) {
    //do something here
}

foreach ($records as $record) {
    //do something here
}
~~~

### Usage with the header

If the `ResultSet::getHeader` is not an empty `array` the found records keys will contains the method returned values.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = (new Statement())->process($reader);
$records->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
foreach ($records as $record) {
    // $records contains the following data
    // array(
    //     'First Name' => 'john',
    //     'Last Name' => 'doe',
    //     'E-mail' => 'john.doe@example.com',
    // );
    //
}
~~~

## Selecting a specific record

If you are only interested in one particular record from the `ResultSet` you can use the `ResultSet::fetchOne` method to return a single record.

~~~php
<?php

public ResultSet::fetchOne(int $nth_record = 0): array
~~~

The `$nth_record` argument represents the nth record contained in the result set starting at `0`. If no argument is given the method will return the first record from the result set. If no record is found an empty `array` is returned.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$stmt = (new Statement())
    ->offset(10)
    ->limit(12)
;
$data = $stmt->process($reader)->fetchOne(3);
// access the 4th record from the recordset (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

## Selecting a single column

~~~php
<?php

public ResultSet::fetchColumn(string|int $columnIndex = 0): Generator
~~~

`ResultSet::fetchColumn` returns a `Generator` of all values in a given column from the `ResultSet` object.

the `$columnIndex` parameter can be:

- an integer representing the column index starting from `0`;
- a string representing one of the value of `ResultSet::getHeader`;

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = (new Statement())->process($reader);
foreach ($records->fetchColumn(2) as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader);
foreach ($records->fetchColumn('E-mail') as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}
~~~

<p class="message-notice">If for a given record the column value is <code>null</code>, the record will be skipped.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$records = (new Statement())->process($reader);
count($records); //returns 10;
count(iterator_to_array($records->fetchColumn(2), false)); //returns 5
//5 records were skipped because the column value is null
~~~

<p class="message-warning">If the <code>ResultSet</code> contains column names and the <code>$columnIndex</code> is not found an <code>Exception</code> exception is thrown.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader);
foreach ($records->fetchColumn('foobar') as $record) {
    //throw an Exception exception if
    //no `foobar` column name is found
    //in $records->getHeader() result
}
~~~

## Selecting key-value pairs

`ResultSet::fetchPairs` method returns a `Generator` of key-value pairs.

~~~php
<?php

public ResultSet::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
~~~

Both arguments, `$offsetIndex` and `$valueIndex` can be:

- an integer which represents the column name index;
- a string representing the value of a column name;

These arguments behave exactly like the `$columnIndex` from `ResultSet::fetchColumn`.

~~~php
<?php

use League\Csv\Reader;

$str = <<EOF
john,doe
jane,doe
foo,bar
sacha
EOF;

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromString($str);
$records = (new Statement())->process($reader);

foreach ($records->fetchPairs() as $firstname => $lastname) {
    // - first iteration
    // echo $firstname; -> 'john'
    // echo $lastname;  -> 'doe'
    // - second iteration
    // echo $firstname; -> 'jane'
    // echo $lastname;  -> 'doe'
    // - third iteration
    // echo $firstname; -> 'foo'
    // echo $lastname; -> 'bar'
    // - fourth iteration
    // echo $firstname; -> 'sacha'
    // echo $lastname; -> 'null'
}
~~~

### Notes

- If no `$offsetIndex` is provided it default to `0`;
- If no `$valueIndex` is provided it default to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;

<p class="message-warning">If the <code>ResultSet</code> contains column names and the submitted arguments are not found an <code>Exception</code> exception is thrown.</p>

## Conversions

### Json serialization

The `ResultSet` class implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object. The interface is implemented using PHP's `iterator_array` on the `ResultSet::getRecords` method. As such, the returned `JSON` string data is affected by the presence or absence of column names.

~~~php
<?php

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
~~~

<p class="message-notice">The record offset <strong>is not preserved on conversion</strong></p>

<p class="message-notice">To convert your CSV records to <code>JSON</code> you must be sure its content is <code>UTF-8</code> encoded, using, for instance, the library <a href="/9.0/converter/charset/">CharsetConverter</a> stream filter.</p>

### Other conversions

If you wish to convert your CSV document in `XML` or `HTML` please refer to the [converters](/9.0/converter/) bundled with this library.
