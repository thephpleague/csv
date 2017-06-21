---
layout: default
title: CSV document Reader connection
---

# Reader Connection

~~~php
<?php

class Reader extends AbstractCsv implements Countable, IteratorAggregate
{
    public function count(): int
    public function fetchAll(): array
    public function fetchColumn(string|int $columnIndex = 0): Generator
    public function fetchOne(int $offset = 0): array
    public function fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
    public function getHeader(): array
    public function getHeaderOffset(): int|null
    public function getIterator(): Iterator
    public function getRecords(array $header = []): Iterator
    public function getRecordPaddingValue(): mixed
    public function setHeaderOffset(?int $offset): self
    public function setRecordPaddingValue(mixed $padding_value): self
}
~~~

The `League\Csv\Reader` class extends the general connections [capabilities](/9.0/connections/) to ease selecting and manipulating CSV document records.

## CSV example

Many examples in this reference require an CSV file. We will use the following file `file.csv` containing the following data:

    "First Name","Last Name",E-mail
    john,doe,john.doe@example.com
    jane,doe,jane.doe@example.com
    john,john,john.john@example.com
    jane,jane

## CSV Header

You can set and retrieve the header offset as well as its corresponding record.

### Description

~~~php
<?php

public Reader::setHeaderOffset(?int $offset): self
public Reader::getHeaderOffset(void): int|null
public Reader::getHeader(void): array
~~~

### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
~~~

### Notes

If no header offset is set:

- `Reader::getHeader` method will return an empty array.
- `Reader::getHeaderOffset` will return `null`.

<p class="message-info">By default no header offset is set.</p>

<p class="message-warning">Because the header is lazy loaded, if you provide a positive offset for an invalid record a <code>RuntimeException</code> will be triggered when trying to access the invalid record.</p>

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
$header_offset = $csv->getHeaderOffset(); //returns 1000
$header = $csv->getHeader(); //triggers a RuntimeException exception
~~~

## Counting CSV records

Because the `Reader` class implements the `Countable` interface you can retrieve to number of records contains in a CSV document using PHP's `count` function. 

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
count($reader); //returns 4
~~~

If a header offset is specified, the number of records will not take into account the header record

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
count($reader); //returns 3
~~~

<p class="message-warning">Using the <code>Countable</code> interface is not recommended for large CSV files</p>

## Iterating over CSV records

~~~php
<?php

public Reader::getRecords(array $header = []): Iterator
public Reader::getIterator(void): Iterator
public Reader::getRecordPaddingValue(): mixed
public Reader::setRecordPaddingValue(mixed $padding_value): self
~~~

### Using Reader::getRecords

The `Reader` class let's you access all its records using the `Reader::getRecords` method. The method returns an `Iterator` containing all CSV document records. It will also:

- Filter out the empty lines;
- Extract the records using the [CSV controls characters](/9.0/connections/controls/);

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'john',
    //  'doe',
    //  'john.doe@example.com'
    // );
    //
}
~~~

### Using Reader::getRecords with Reader::setHeaderOffset

If you specify the CSV header offset using `setHeaderOffset`, the found record will be combined to each CSV record to return an associated array whose keys are composed of the header values.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
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
    //
}
~~~

### Using Reader::getRecords with its optional argument

Conversely, you can submit your own header record using the optional `$header` argument of the `getRecords` method.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstame' => 'jane',
    //  'lastname' => 'doe',
    //  'email' => 'jane.doe@example.com'
    // );
}
~~~

<p class="message-notice">The optional <code>$header</code> argument from  the <code>Reader::getRecords</code> takes precedence over the header offset property but its corresponding record will still be removed from the returned <code>Iterator</code>.</p>

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstame' => 'jane',
    //  'lastname' => 'doe',
    //  'email' => 'jane.doe@example.com'
    // );
}
//the first record will still be skip!!
~~~

<p class="message-warning">In both cases, if the header record contains non unique string values, a <code>RuntimeException</code> exception is triggered.</p>

### Using the IteratorAggregate interface

Because the `Reader` class implements the `IteratorAggregate` interface you can directly iterate over each record using the `foreach` construct and an instantiated `Reader` object.  
You will get the same results as if you had called `Reader::getRecords` without its optional argument.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
foreach ($reader as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'First Name' => 'john',
    //  'Last Name' => 'doe',
    //  'E-mail' => john.doe@example.com'
    // );
    //
}
~~~

## CSV Record normalization

The returned records are normalized using the following rules:

- The BOM sequence is removed if present;
- [Stream filters](/9.0/connections/filters/) are applied if present
- If a header record was provided, the number of fields is normalized to the number of fields contained in that record:
    - Extra fields are truncated.
    - Missing fields are added with a default padding value.

The default padding value can be defined using the `setRecordPaddingValue` method. By default, if no content is specified `null` will be used. You can retrieve the padding value using the `getRecordPaddingValue` method.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$reader->setRecordPaddingValue('N/A')
$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'First Name' => 'jane',
    //  'Last Name' => 'jane',
    //  'E-mail' => 'N/A'
    // );
    //
}
$reader->getRecordPaddingValue(); //returns 'N/A'
~~~

## Selecting CSV records

### Simple Usage

~~~php
<?php

public Reader::fetchAll(): array
public Reader::fetchColumn(string|int $columnIndex = 0): Generator
public Reader::fetchOne(int $offset = 0): array
public Reader::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
~~~

Using method overloading, you can directly access all retrieving methods attached to the [ResultSet](/9.0/reader/resultset/#iterating-over-the-result-set) object.

#### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');

$records = $reader->fetchColumn(2);
//$records is a Generator representing all the fields of the CSV 3rd column
~~~

<p class="message-notice">The original record offset <strong>is not preserved</strong>.</p>

### Advanced Usage

If you require a more advance record selection **or want to preserve the original record offset**, you should use a [Statement](/9.0/reader/statement/) object to process the `Reader` object. The found records are returned as a [ResultSet](/9.0/reader/resultset) object.

#### Example

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$stmt = (new Statement())
    ->offset(3)
    ->limit(5)
;

$records = $stmt->process($reader);
//$records is a League\Csv\ResultSet object
~~~