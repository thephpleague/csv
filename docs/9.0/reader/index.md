---
layout: default
title: CSV document Reader connection
---

# Reader Connection

~~~php
<?php

public Reader::getHeaderOffset(void): int|null
public Reader::getHeader(void): array
public Reader::setHeaderOffset(?int $offset): Reader
public Reader::getIterator(void): Iterator
public Reader::select(Statement $stmt = null): RecordSet
public Reader::fetchDelimitersOccurrence(array $delimiters, int $nbRows = 1): array
~~~

The `League\Csv\Reader` class extends the general connections [capabilities](/9.0/connections/) to ease selecting and manipulating CSV document records.


## CSV example

Many examples in this reference require an CSV file. We will use `/path/to/file.csv` that contains the following data represented in an HTML Table for ease

| First Name | Last Name | E-mail               |
| -----------|-----------|----------------------|
|    john    |  doe      | john.doe@example.com |
|    jane    |  doe      | jane.doe@example.com |


## Header detection

You can set and retrieve the header offset as well as the header record.

### Description

~~~php
<?php

public Reader::setHeaderOffset(?int $offset): Reader
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

Because the header is lazy loaded, if you provide a positive offset for an invalid record a `RuntimeException` will be triggered when trying to access the invalid record.

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
$header_offset = $csv->getHeaderOffset(); //returns 1000
$header = $csv->getHeader(); //triggers a Exception
~~~

## Iterating over the document records

Because the `Reader` class implements the `IteratorAggregate` interface you can iterate over each record using the `foreach` construct. While iterating the `Reader` will:

- Filter out the empty lines;
- Remove the BOM sequence if present;
- Extract the records using the CSV controls attributes;
- Apply the stream filters if supplied;
- Attach the header value as keys to each record array if a valid header is found;

### Example without a header

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
foreach ($reader as $offset => $record) {
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

### Example with a header

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
foreach ($reader as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'Fist Name' => 'john',
    //  'Last Name' => 'doe',
    //  'E-mail' => john.doe@example.com'
    // );
    //
}
~~~

<p class="message-notice">If a record header is selected, it will be skipped from the iteration.</p>

## Selecting CSV records

To improve records selection you can use the `Reader::select` method. This methods takes a `League\Csv\Statement` object and returns a `League\Csv\RecordSet` object containing the selected records.

~~~php
<?php

public Reader::select(Statement $stmt = null): RecordSet
~~~

### Example

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$stmt = (new Statement())
    ->offset(3)
    ->limit(5)
;
$records = $reader->select($stmt);
//$records is a League\Csv\RecordSet object
~~~

<p class="message-info"><strong>Tips:</strong> The resulting records can be further manipulated by the <a href="/9.0/reader/statement/">Statement</a> and/or the <a href="/9.0/reader/records/">RecordSet</a> classes. Please refer to their documentation for more informations.</p>

## Detecting the delimiter character

This method allow you to find the occurences of some delimiters in a given CSV object.

~~~php
<?php

public Reader::fetchDelimitersOccurrence(array $delimiters, int $nbRows = 1): array
~~~

The method takes two arguments:

* an array containing the delimiters to check;
* an integer which represents the number of rows to scan (default to `1`);

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setEnclosure('"');
$reader->setEscape('\\');

$delimiters_list = $reader->fetchDelimitersOccurrence([' ', '|'], 10);
// $delimiters_list can be the following
// [
//     '|' => 20,
//     ' ' => 0,
// ]
// This seems to be a consistent CSV with:
// - the delimiter "|" appearing 20 times in the 10 first rows
// - the delimiter " " never appearing
~~~

<p class="message-warning"><strong>Warning:</strong> This method only test the delimiters you gave it.</p>

