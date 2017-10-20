---
layout: default
title: CSV document Reader connection
---

# Reader Connection

~~~php
<?php

class Reader extends AbstractCsv implements Countable, IteratorAggregate, JsonSerializable
{
    public function fetchColumn(string|int $columnIndex = 0): Generator
    public function fetchOne(int $nth_record = 0): array
    public function fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
    public function getHeader(): array
    public function getHeaderOffset(): ?int
    public function getRecords(array $header = []): Iterator
    public function setHeaderOffset(?int $offset): self
}
~~~

The `League\Csv\Reader` class extends the general connections [capabilities](/9.0/connections/) to ease selecting and manipulating CSV document records.

<p class="message-notice">Starting with version <code>9.1.0</code>, <code>createFromPath</code> when used from the <code>Reader</code> object will have its default set to <code>r</code>.</p>

<p class="message-notice">Prior to <code>9.1.0</code>, by default, the mode for a <code>Reader::createFromPath</code> is <code>r+</code> which looks for write permissions on the file and throws an <code>Exception</code> if the file cannot be opened with the permission set. For sake of clarity, it is strongly suggested to set <code>r</code> mode on the file to ensure it can be opened.</p>

## CSV example

Many examples in this reference require an CSV file. We will use the following file `file.csv` containing the following data:

    "First Name","Last Name",E-mail
    john,doe,john.doe@example.com
    jane,doe,jane.doe@example.com
    john,john,john.john@example.com
    jane,jane

## CSV header

You can set and retrieve the header offset as well as its corresponding record.

### Description

~~~php
<?php

public Reader::setHeaderOffset(?int $offset): self
public Reader::getHeaderOffset(void): ?int
public Reader::getHeader(void): array
~~~

### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
~~~

If no header offset is set:

- `Reader::getHeader` method will return an empty array.
- `Reader::getHeaderOffset` will return `null`.

<p class="message-info">By default no header offset is set.</p>

<p class="message-warning">Because the header is lazy loaded, if you provide a positive offset for an invalid record a <code>Exception</code> exception will be triggered when trying to access the invalid record.</p>

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
$header_offset = $csv->getHeaderOffset(); //returns 1000
$header = $csv->getHeader(); //triggers a Exception exception
~~~

## CSV records

~~~php
<?php

public Reader::getRecords(array $header = []): Iterator
~~~

### Reader::getRecords basic usage

The `Reader` class let's you access all its records using the `Reader::getRecords` method. The method returns an `Iterator` containing all CSV document records. It will also:

- Filter out the empty lines;
- Extract the records using the [CSV controls characters](/9.0/connections/controls/);

~~~php
<?php

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
    //
}
~~~

### Reader::getRecords with Reader::setHeaderOffset

If you specify the CSV header offset using `setHeaderOffset`, the found record will be combined to each CSV record to return an associated array whose keys are composed of the header values.

~~~php
<?php

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
    //
}
~~~

### Reader::getRecords with its optional argument

Conversely, you can submit your own header record using the optional `$header` argument of the `getRecords` method.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
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

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
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

<p class="message-warning">In both cases, if the header record contains non unique string values, a <code>Exception</code> exception is triggered.</p>

### Using the IteratorAggregate interface

Because the `Reader` class implements the `IteratorAggregate` interface you can directly iterate over each record using the `foreach` construct and an instantiated `Reader` object.  
You will get the same results as if you had called `Reader::getRecords` without its optional argument.

~~~php
<?php

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
    //
}
~~~

## Records normalization

The returned records are normalized using the following rules:

- [Stream filters](/9.0/connections/filters/) are applied if present
- The BOM sequence is removed if present;
- If a header record was provided, the number of fields is normalized to the number of fields contained in that record:
    - Extra fields are truncated.
    - Missing fields are added with a `null` value.

~~~php
<?php

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
    //
}
~~~

## Records count

You can retrieve the number of records contains in a CSV document using PHP's `count` function because the `Reader` class implements the `Countable` interface.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
count($reader); //returns 4
~~~

If a header offset is specified, the number of records will not take into account the header record.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
count($reader); //returns 3
~~~

<p class="message-notice">The <code>Countable</code> interface is implemented using PHP's <code>iterator_count</code> on the <code>Reader::getRecords</code> method.</p>

## Records selection

### Simple Usage

~~~php
<?php

public Reader::fetchColumn(string|int $columnIndex = 0): Generator
public Reader::fetchOne(int $nth_record = 0): array
public Reader::fetchPairs(string|int $offsetIndex = 0, string|int $valueIndex = 1): Generator
~~~

Using method overloading, you can directly access all retrieving methods attached to the [ResultSet](/9.0/reader/resultset/#records) object.

#### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');

$records = $reader->fetchColumn(2);
//$records is a Generator representing all the fields of the CSV 3rd column
~~~

### Advanced Usage

If you require a more advance record selection, you should use a [Statement](/9.0/reader/statement/) object to process the `Reader` object. The found records are returned as a [ResultSet](/9.0/reader/resultset) object.

#### Example

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$stmt = (new Statement())
    ->offset(3)
    ->limit(5)
;

$records = $stmt->process($reader);
//$records is a League\Csv\ResultSet object
~~~

## Records conversion

### Json serialization

The `Reader` class implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object. The interface is implemented using PHP's `iterator_array` on the `Reader::getRecords` method. As such, the returned `JSON` string data depends on the presence or absence of a header.

~~~php
<?php

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
~~~

<p class="message-notice">The record offset <strong>is not preserved on conversion</strong></p>

<p class="message-notice">To convert your CSV to <code>JSON</code> you must be sure its content is <code>UTF-8</code> encoded, using, for instance, the library <a href="/9.0/converter/charset/">CharsetConverter</a> stream filter.</p>

### Other conversions

If you wish to convert your CSV document in `XML` or `HTML` please refer to the [converters](/9.0/converter/) bundled with this library.
