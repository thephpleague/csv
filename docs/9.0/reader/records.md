---
layout: default
title: Accessing Records from a CSV document
---

# Records Collection

~~~php
<?php
public RecordSet::count(): int
public RecordSet::isRecordOffsetPreserved(): bool
public RecordSet::getColumnNames(): array
public RecordSet::getIterator(): Generator
public RecordSet::fetchAll(): array
public RecordSet::fetchOne(int $offset = 0): array
public RecordSet::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
public RecordSet::fetchColumn(string|int $columnIndex = 0): Generator
public RecordSet::preserveRecordOffset(bool $status): RecordSet
~~~

The `League\Csv\RecordSet` is a class which manipulates a collection of CSV document records. This object is returned from [Reader::select](/9.0/reader/#selecting-csv-records) or [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

## Collection informations

~~~php
<?php
public RecordSet::count(): int
public RecordSet::getColumnNames(): array
~~~

The `RecordSet` class implements implements the `Countable` interface.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
count($records); //return the total number of records found
~~~

`RecordSet::getColumnNames` returns the columns name information associated with the current object. This is usefull if the `RecordSet` object was created from:

- a `Reader` object where [Reader::getHeader](/9.0/reader/#header-detection) is not empty;
- and/or a `Statement` object where [Statement::columns](/9.0/reader/statement/#select-constraint) was used.

### Example: no header information was given

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
$records->getColumnNames(); // is empty because no header information was given
~~~

### Example: header information given by the Reader object

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = (new Statement())->process($reader);
$records->getColumnNames(); // returns ['First Name', 'Last Name', 'E-mail'];
~~~

### Example: header information overridden by the Statement object

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$stmt = (new Statement())
    ->columns([
        'First Name' => 'firstname',
        'Last Name' => 'lastname',
        'E-mail' => 'email',
    ])
;
$records = $stmt->process($reader);
$records->getColumnNames(); // returns ['firstname', 'lastname', 'email'];
~~~

## Collection options

~~~php
<?php

public RecordSet::isRecordOffsetPreserved(): bool
public RecordSet::preserveRecordOffset(bool $status): RecordSet
~~~

`RecordSet::preserveRecordOffset` indicates if the `RecordSet` must keep the original CSV document records offset or can re-index them. When the `$status` is `true`, the original CSV document record offset will be preserve and output in methods where it makes sense.

At any given time you can tell whether the CSV document offset is kept by calling `RecordSet::isRecordOffsetPreserved` which returns a boolean.

<p class="message-notice">By default, the <code>RecordSet</code> object does not preserve the original offset.</p>

## Iterating over the collection

~~~php
<?php

public RecordSet::getIterator(): Generator
public RecordSet::fetchAll(): array
~~~

Because the `RecordSet` class implements the `IteratorAggregate` interface  you can iterate over each record using the `foreach` construct or by calling directly `RecordSet::getIterator`.

`RecordSet::fetchAll` behaves exactly like `RecordSet::getIterator` with **one difference**, it returns a sequential `array` of all records instead of the memory efficient `Generator` returned by `RecordSet::getIterator`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
foreach ($records as $offset => $record) {
    //do something here
}

foreach ($records->fetchAll() as $offset => $record) {
    //do something here
}

~~~

If the `RecordSet::preserveRecordOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain a numerical index starting from `0`.

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
    //the first iteration will give $offset equal to `0`
}

$records->preserveRecordOffset(true); //we are preserving the original offset
$records->isRecordOffsetPreserved(); //returns true
foreach ($records->fetchAll() as $offset => $record) {
    //the first iteration will give $offset equal to `5`
}
~~~

If the `Reader::getHeader` is not an empty `array` or if the `Statement::columns` was used each record field key will contains the corresponding column name.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$stmt = (new Statement())
    ->columns([
        'First Name' => 'firstname',
        'Last Name' => 'lastname',
        'E-mail' => 'email',
    ])
;
$records = $stmt->process($reader);
$records->getColumnNames(); //returns ['firstname', 'lastname', 'email']
foreach ($records as $record) {
    // $records contains the following data
    // array(
    //     'firstname' => 'john',
    //     'lastname' => 'doe',
    //     'email' => 'john.doe@example.com',
    // );
    //
}
~~~

## Selecting a specific record

If you are only interested in on particular record from the `RecordSet` you can use the `RecordSet::fetchOne` method to return a single record.

~~~php
<?php

public RecordSet::fetchOne(int $offset = 0): array
~~~

The required argument `$offset` represents the record offset in the record collection starting at `0`. If no argument is given the method will return the first record from the result set. If no record is found an empty `array` is returned.

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

<p class="message-notice">The <code>$offset</code> argument is not affected by <code>RecordSet::preserveRecordOffset</code> status.</p>

## Selecting a specific column

`RecordSet::fetchColumn` returns a `Generator` of all values in a given column from the `RecordSet` object.

~~~php
<?php

public RecordSet::fetchColumn(string|int $columnIndex = 0): Generator
~~~

the `$columnIndex` parameter can be:

- an integer which represents the column name index;
- a string representing the value of a column name;

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
foreach ($records->fetchColumn(2) as $offset => $value) {
    //$value is a string representing the value
    //of a given record for the selected column
}

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = (new Statement())->process($reader);
foreach ($records->fetchColumn('First Name') as $offset => $value) {
    //$value may be equal to 'john'
}
~~~

If the `RecordSet::preserveRecordOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain numerical index starting from`0`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');

 //we will start iterating from the 6th record
$stmt = (new Statement())->offset(5);
$records = $stmt->process($reader);
$records->preserveRecordOffset(true);
foreach ($records->fetchColumn(2) as $offset => $value) {
    //$value is a string representing the value
    //of a given record for the selected column
    //the first iteration will give $offset equal to `5`
    //if the record contains the selected column
}
~~~

<p class="message-notice">If for a given record the column does not exist, the record will be skipped.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = (new Statement())->process($reader);
count($records); //returns 10;
count(iterator_to_array($records->fetchColumn(2), false)); //returns 5
//5 records were skipped because the value column did not exists
~~~

<p class="message-warning">If the <code>$columnIndex</code> is not found a <code>InvalidArgumentExceptionw</code> may be thrown.</p>

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

`RecordSet::fetchPairs` method returns a `Generator` of key-value pairs.

~~~php
<?php

public RecordSet::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
~~~

Both arguments, `$offsetIndex` and `$valueIndex` can be:

- an integer which represents the column name index;
- a string representing the value of a column name;

These arguments behave exactly like the `$columnIndex` from `RecordSet::fetchColumn`.

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
foreach ((new Statement())->process($reader)->fetchPairs() as $firstname => $lastname) {
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