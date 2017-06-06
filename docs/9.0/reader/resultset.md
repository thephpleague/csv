---
layout: default
title: Accessing Records from a CSV document
---

# Result Set

~~~php
<?php

class ResultSet implements Countable, IteratorAggregate
{
    public function count(): int
    public function fetchAll(): array
    public function fetchColumn(string|int $columnIndex = 0): Generator
    public function fetchOne(int $offset = 0): array
    public function fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
    public function getColumnNames(): array
    public function getIterator(): Generator
    public function isRecordOffsetPreserved(): bool
    public function preserveRecordOffset(bool $status): self
}
~~~

A `League\Csv\ResultSet` object represents the associated result set of processing a [CSV document](/9.0/reader/) with a [constraint builder](/9.0/reader/statement/). This object is returned from [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

## Result set informations

### Accessing the result set column names

~~~php
<?php
public ResultSet::getColumnNames(): array
~~~

`ResultSet::getColumnNames` returns the columns name information associated with the current object. This is usefull if the `ResultSet` object was created from a `Reader` object where [Reader::getHeader](/9.0/reader/#header-detection) is not empty;

#### Example: no header information was given

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
$records->getColumnNames(); // is empty because no header information was given
~~~

#### Example: header information given by the Reader object

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader);
$records->getColumnNames(); // returns ['First Name', 'Last Name', 'E-mail'];
~~~

### Accessing the number of records in the result set

The `ResultSet` class implements implements the `Countable` interface.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
count($records); //return the total number of records found
~~~

## Result set options

### Preserving the original CSV record offset

~~~php
<?php

public ResultSet::isRecordOffsetPreserved(): bool
public ResultSet::preserveRecordOffset(bool $status): self
~~~

`ResultSet::preserveRecordOffset` indicates if the `ResultSet` must keep the original CSV document records offset or can re-index them. When the `$status` is `true`, the original CSV document record offset will be preserved and outputted in methods where it makes sense.

At any given time you can tell whether the CSV document offset is kept by calling `ResultSet::isRecordOffsetPreserved` which returns a boolean.

<p class="message-notice">By default, the <code>ResultSet</code> object does not preserve the original offset.</p>

## Iterating over the result set

### Description

~~~php
<?php

public ResultSet::fetchAll(void): array
~~~

To iterate over each found records you can:

- call the `ResultSet::fetchAll` method which returns a sequential `array` of all records found;
- directly use the `foreach` construct as the class implements the `IteratorAggregate` interface;

<p class="message-info">The <code>foreach</code> construct enables using the memory efficient <code>Generator</code> object.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
foreach ($records as $record) {
    //do something here
}

foreach ($records->fetchAll() as $record) {
    //do something here
}

~~~

### Accessing the CSV document record offset

If the `ResultSet::preserveRecordOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain a numerical index starting from `0`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');

 //we will start iterating from the 6th record
$stmt = (new Statement())->offset(5);
$records = $stmt->process($reader);
$records->isRecordOffsetPreserved(); //returns false
foreach ($records as $offset => $record) {
    //during the first iteration $offset will be equal to 0
}

$records->preserveRecordOffset(true); //we are preserving the original offset
$records->isRecordOffsetPreserved(); //returns true
foreach ($records->fetchAll() as $offset => $record) {
    //during the first iteration $offset will be equal to 5
}
~~~

### Usage with the column names

If the `ResultSet::getColumnNames` is not an empty `array` the found records keys will contains the method returned values.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = (new Statement())->process($reader);
$records->getColumnNames(); //returns ['First Name', 'Last Name', 'E-mail']
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

public ResultSet::fetchOne(int $offset = 0): array
~~~

The `$offset` argument represents the record offset in the result set starting at `0`. If no argument is given the method will return the first record from the result set. If no record is found an empty `array` is returned.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);

$stmt = (new Statement())
    ->offset(10)
    ->limit(12)
;
$data = $stmt->proce($reader)->fetchOne(3);
// access the 4th record from the recordset (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

<p class="message-notice">The <code>$offset</code> argument is not affected by <code>ResultSet::preserveRecordOffset</code> status.</p>

## Selecting a specific column

~~~php
<?php

public ResultSet::fetchColumn(string|int $columnIndex = 0): Generator
~~~

`ResultSet::fetchColumn` returns a `Generator` of all values in a given column from the `ResultSet` object.

the `$columnIndex` parameter can be:

- an integer representing the column index starting from `0`;
- a string representing one of the value of `ResultSet::getColumnNames`;

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
foreach ($records->fetchColumn(2) as $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //$value may be equal to 'john.doe@example.com'
}

$reader = Reader::createFromPath('/path/to/my/file.csv');
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

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
count($records); //returns 10;
count(iterator_to_array($records->fetchColumn(2), false)); //returns 5
//5 records were skipped because the column value is null
~~~

<p class="message-info">The method is affected by <code>ResultSet::preserveRecordOffset</code></p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');

 //we will start iterating from the 6th record
$stmt = (new Statement())->offset(5);
$records = $stmt->process($reader);
$records->preserveRecordOffset(true);
foreach ($records->fetchColumn(0) as $offset => $value) {
    //$value is a string representing the value
    //of a given record for the column 0
    //the first iteration will give $offset equal to 5
    //if the record contains the selected column
}
~~~

<p class="message-warning">If the <code>ResultSet</code> contains column names and the <code>$columnIndex</code> is not found a <code>RuntimeException</code> is thrown.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);

$records = (new Statement())->process($reader);
foreach ($records->fetchColumn('foobar') as $record) {
    //throw an InvalidArgumentException if
    //no `foobar` column name is found
    //in $records->getColumnNames() result
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

<p class="message-warning">If the <code>ResultSet</code> contains column names and the submitted arguments are not found a <code>RuntimeException</code> is thrown.</p>