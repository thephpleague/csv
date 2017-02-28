---
layout: default
title: Accessing Records from a CSV document
---

# Selecting Records

To select CSV records you are required to use the `League\Csv\Reader`.

~~~php
<?php

public Reader::fetchDelimitersOccurrence(array $delimiters, int $nbRows = 1): array
public Reader::getHeaderOffset(void): int|null
public Reader::getHeader(void): array
public Reader::getIterator(void): Iterator
public Reader::select(Statement $stmt = null): RecordSet
public Reader::setHeaderOffset(int $offset = null): Reader
~~~

The `Reader` class exposes the general connections <a href="/9.0/connections/">methods</a>  and <a href="/9.0/attributes/">attributes</a>.

## CSV document detection and formatting

The `Reader` class performs a number of action to ease selecting records from your CSV document. When used the `Reader` will:

- Extract the records using the CSV controls attributes;
- Filter out the empty lines;
- Apply the stream filters if necessary;
- Remove the BOM sequence if present;
- Attach the header value as keys to each record value if a valid header is found;

To do so the `Reader` exposes specific connection attributes:

### fetchDelimitersOccurrence

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

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
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

### CSV document header detection.

You can set and retrieve the header offset as well as the header record.

#### Description

~~~php
<?php

public Reader::setHeaderOffset(int $offset = null): Reader
public Reader::getHeaderOffset(void): int|null
public Reader::getHeader(void): array
~~~

#### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns an array
~~~

#### Notes

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

## Accessing the CSV document records

### Iterate over the Reader class

the `Reader` class implements the `IteratorAggregate` interface, so If you only want to iterate over each records you can use the `foreach` construct to do so.

Depending on the presence of a CSV header or not, the record array will be formatted differently.

#### Example without a header

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

#### Example with a header

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
foreach ($reader as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstname' => 'john',
    //  'lastname' => 'doe',
    //  'email' => john.doe@example.com'
    // );
    //
}
~~~

<p class="message-notice">When a record header is selected, this record is removed from the iteration.</p>

### Reader::select

To improve records selection you can use the `Reader::select` method. This methods takes a `League\Csv\Statement` object and returns a `League\Csv\RecordSet` object containing the selected records.

~~~php
<?php

public Reader::select(Statement $stmt = null): RecordSet
~~~

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
$records = $reader->select($stmt);

$records->fetchAll(); //returns an associated array of all the selected records
$records->toXML(); //returns the DOMDocument representations of the selected records
~~~

<p class="message-info"><strong>Tips:</strong> The resulting records can be further manipulated by the <code>Statement</code> and/or the <code>RecordSet</code> classes. Please refer to their documentation for more informations.</p>

## Constraint Builder

The `League\Csv\Statement` class is a constraint builder to help ease selecting records from a CSV document created using the `League\Csv\Reader` class.

~~~php
<?php

public Statement::where(callable $callable): Statement
public Statement::orderBy(callable $callable): Statement
public Statement::offset(int $offset): Statement
public Statement::limit(int $limit): Statement
public Statement::columns(array $columns): Statement
public Statement::process(Reader $reader): RecordSet
~~~

When building a constraint, the methods do not need to be called in any particular order, and may be called multiple times. Because the `Statement` object is immutable, each time its constraint methods are called they will return a new `Statement` object without modifying the current `Statement` object.

<p class="message-info"><strong>Tips:</strong> Because the <code>Statement</code> object is independent of the <code>League\Csv\Reader</code> object it can be re-use on multiple <code>Reader</code> objects.</p>

~~~php
<?php

$stmt = (new Statement())
    ->columns([
         0 => 'firstname',
         3 => 'lastname',
         1 => 'email',
    ])
    ->offset(3)
    ->limit(10)
    ->where(function (array $record, int $offset) {
        return $record[0] == 'john' && $offset % 2 == 0;
    })
    ->orderBy(function(array $recordA, array $recordB) {
        return $recordA[2] <=> $recordB[2];
    })
;
~~~

### Statement::where

The filters attached using the `Statement::where` method **are the first settings applied to the CSV before anything else**. This option follow the *First In First Out* rule.

~~~php
<?php

public Statement::where(callable $callable): Statement
~~~

The callable filter signature is as follows:

~~~php
<?php

function(array $record [, int $offset [, Iterator $iterator]]): Statement
~~~

It takes up to three parameters:

- `$record`: the CSV current record as an array
- `$offset`: the CSV current record offset
- `$iterator`: the current CSV iterator

### Statement::orderBy

The sorting options are applied **after the `Statement::where` options**. The sorting follows the *First In First Out* rule.

<p class="message-warning"><strong>Warning:</strong> To sort the data <code>iterator_to_array</code> is used, which could lead to a performance penalty if you have a heavy CSV file to sort
</p>


`Statement::orderBy` method adds a sorting function each time it is called.

~~~php
<?php

public Statement::orderBy(callable $callable): Statement
~~~

The callable sort function signature is as follows:

~~~php
<?php

function(array $recordA, array $recordB): int
~~~

The sort function takes exactly two parameters, which will be filled by pairs of records.

### Interval constraint methods

The interval methods enable returning a specific interval of CSV records. When called more than once, only the last filtering settings is taken into account. The interval is calculated **after applying `Statement::where` and `Statement::orderBy` options**.

The interval API is made of the following method

~~~php
<?php

public Statement::offset(int $offset): Statement
public Statement::limit(int $limit): Statement
~~~

`Statement::offset` specifies an optional offset for the return data. By default if no offset was provided the offset equals `0`.

`Statement::Limit` specifies an optional maximum records count for the return data. By default if no limit is provided the limit equals `-1`, which translate to all records.

<p class="message-notice">When called multiple times, each call override the last settings for these options.</p>

### Statement::columns

This option enables selecting specific columns from each record.

~~~php
<?php

public Statement::columns(array $columns): Statement
~~~

The single parameter is an associative array where:

- the key represents the specified key from the `Reader`
- the value represents the key alias to be used by the `RecordSet` object.

The `Statement::columns` option is the last to be applied. So you can not use the alias with the `Statement::where` or the `Statement::orderBy` methods.

<p class="message-info"><strong>Tips:</strong> To reset the <code>columns</code> value, you need to provide an empty array.</p>

<p class="message-notice">When called multiple times, each call override the last settings for this option.</p>


#### If the Reader object has no header

~~~php
<?php

use League\Csv\Statement;

$stmt = (new Statement())
    ->columns(['firstname', 'lastname', 'email'])
;

// is equivalent to:

$stmt = (new Statement())
    ->columns([
        0 => 'firstname',
        1 => 'lastname',
        2 => 'email',
    ])
;
~~~

#### If the Reader object has a header

~~~php
<?php

use League\Csv\Statement;

$stmt = (new Statement())
    ->columns(['firstname', 'lastname', 'email'])
;

// is equivalent to:

$stmt = (new Statement())
    ->columns([
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'email' => 'email',
    ])
;
~~~

<p class="message-warning">If a the <code>Reader</code> object has a header an the column uses undefined header value a <code>RuntimeException</code> is triggered.</p>

### Statement::process

~~~php
<?php

public Statement::process(Reader $reader): RecordSet
~~~

This method process a `Reader` object and returns a `RecordSet` object.

<p class="message-info"><strong>Tips:</strong> this method is equivalent of <code>Reader::select</code>.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

function filterByEmail($row)
{
    return filter_var($row[2], FILTER_VALIDATE_EMAIL);
}

function sortByLastName($rowA, $rowB)
{
    return strcmp($rowB[1], $rowA[1]);
}

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->addStreamFilter('string.toupper');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
    ->where('filterByEmail')
    ->orderBy('sortByLastName')
    ->columns(['firstname', 'lastname', 'email'])
;

$records = $stmt->process($reader);
$data = $records->fetchAll();
// data length will be equals or lesser that 2 starting from the row index 3.
// will return something like this :
//
// [
//   ['firstname' => 'JANE', 'lastname' => 'RAMANOV', 'email' => 'JANE.RAMANOV@EXAMPLE.COM'],
//   ['firstname' => 'JOHN', 'lastname' => 'DOE', 'email' => 'JOHN.DOE@EXAMPLE.COM'],
// ]
//
~~~

## Records Collection

The `League\Csv\RecordSet` is a class which manipulates the CSV document records as selected
from a `League\Csv\Reader` using a `League\Csv\Statement` object.

### Collection informations

~~~php
<?php
public RecordSet::count(): int
public RecordSet::getColumnNames(): array
~~~

The `RecordSet` class implements implements the `Countable` interface.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->select();
count($results); //return the total number of records found
~~~

`RecordSet::getColumnName` and `RecordSet::getColumnNames` returns the columns name information associated with the current object. This is usefull if the `RecordSet` object was created from:

- a `Reader` object where `Reader::getHeader` is not empty;
- a `Statement` object where `Statement::columns` was used.

#### Example: no header information was given

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->select();
$records->getColumnNames(); // is empty because no header information was given
~~~

#### Example: header information given by the Reader object

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->select();
$records->getColumnNames(); // returns ['firstname', 'lastname', 'email'];
~~~

#### Example: header information overridden by the Statement object

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$stmt = (new Statement())
->columns([
    'firstname' => 'First Name',
    'lastname' => 'Last Name',
    'email' => 'E-mail',
]);
$records = $reader->select();
$records->getColumnNames(); // returns ['First Name', 'Last Name', 'E-mail'];
~~~

### Collection options

~~~php
<?php

public RecordSet::preserveOffset(bool $status): RecordSet
public RecordSet::setConversionInputEncoding(string $charset): RecordSet
~~~

`RecordSet::preserveOffset` preserve or not the original CSV document records offset.
When the `$status` is `true`, the original CSV document record offset will be preserve in methods output where it makes sense.

<p class="message-notice">By default, the <code>RecordSet</code> object does not preserve the original offset.</p>

`RecordSet::setConversionInputEncoding` performs a charset conversion so that the records are all in `UTF-8` prior to converting the collection into XML or JSON. Without this transcoding, errors may occurs while converting your data.

<p class="message-notice">By default, the <code>RecordSet</code> object expect your records to be in <code>UTF-8</code>.</p>

<p class="message-info"><strong>Tips:</strong> if the <code>Reader</code> supports stream filtering, use <code>Reader::addStreamFilter</code> instead to perform this charset conversion.</p>

### Iterating over the collection

~~~php
<?php

public RecordSet::getIterator(): Generator
public RecordSet::fetchAll(): array
~~~

This `RecordSet` class implements implements the `IteratorAggregate` interface using the `RecordSet::getIterator` method.

`RecordSet::fetchAll` behaves exactly like `RecordSet::getIterator` with **one difference**, it returns a sequential `array` of all records instead of the memory efficient `Generator` returned by `RecordSet::getIterator`.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->select();
foreach ($results as $offset => $record) {
    //do something here
}

foreach ($results->fetchAll() as $offset => $record) {
    //do something here
}

~~~

If the `RecordSet::preserveOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain numerical index starting from`0`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;


$reader = Reader::createFromPath('/path/to/my/file.csv');

 //we will start iterating from the 6th record
$stmt = (new Statement())->offset(5);
$results = $reader->select($stmt);

foreach ($results as $offset => $record) {
    //the first iteration will give $offset equal to `0`
}

$results->preserveOffset(true); //we are preserving the original offset
foreach ($results->fetchAll() as $offset => $record) {
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
        'firstname' => 'First Name',
        'lastname' => 'Last Name',
        'email' => 'E-mail',
    ])
;
$records = $reader->select();
$reader->getColumnNames(); //returns ['First Name', 'Last Name', 'E-mail']
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

### Selecting a specific record

If you are only interested in on particular record from the `RecordSet` you can use the `RecordSet::fetchOne` method to return a single record.

~~~php
<?php

public RecordSet::fetchOne($offset = 0): array
~~~

The required argument `$offset` represents the record offset in the collection starting at `0`. If no argument is given the method will return the first record from the result set. If no record is found an empty `array` is returned.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$stmt = (new Statement())
    ->offset(10)
    ->limit(12)
;
$data = $reader->select()->fetchOne(3);
// access the 4th record from the recordset (indexing starts at 0)
// will return something like this :
//
//   ['john', 'doe', 'john.doe@example.com']
//
~~~

<p class="message-notice">The <code>$offset</code> argument is not affected by <code>RecordSet::preserveOffset</code> status.</p>


### Selecting a specific column

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

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->select();
$result = $records->fetchColumn(2);
foreach ($result as $offset => $value) {
    //$value is a string representing the value
    //of a given record for the selected column
}

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->select();
$result = $records->fetchColumn('firstname');
foreach ($result as $offset => $value) {
    //$value may be equal to 'john'
}
~~~

If the `RecordSet::preserveOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain numerical index starting from`0`.

~~~php
<?php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/my/file.csv');

 //we will start iterating from the 6th record
$stmt = (new Statement())->offset(5);
$records = $reader->select($stmt);
$records->preserveOffset(true);
$result = $records->fetchColumn(2);
foreach ($result as $offset => $value) {
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

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->select();
$result = $$records->fetchColumn(2);
count($records); //returns 10;
count(iterator_to_array($result, false)); //returns 5
//5 records were skipped because the value column did not exists
~~~

<p class="message-warning">If the <code>$columnIndex</code> is not found a <code>InvalidArgumentExceptionw</code> may be thrown.</p>

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->select();
foreach ($records->fetchColumn('foobar') as $record) {
    //throw an InvalidArgumentException if
    //no `foobar` column name is found
    //in $records->getColumnNames() result
}
~~~

### Selecting key-value pairs

`RecordSet::fetchPairs` method returns a `Generator` of key-value pairs.

~~~php
<?php

public RecordSet::fetchPairs(
    string|int $offsetIndex = 0,
    string|int $valueIndex = 1
): Generator
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

$reader = Reader::createFromString($str);
foreach ($reader->select()->fetchPairs() as $firstname => $lastname) {
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

#### Notes

- If no `$offsetIndex` is provided it default to `0`;
- If no `$valueIndex` is provided it default to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;


### Converting the collection into JSON/XML/HTML

To convert your the `RecordSet` collection into JSON, XML and HTML formats your records collection must be `UTF-8` encoded.

- If your `Reader` object supports PHP stream filters then it's recommended to use the library stream filtering mechanism to convert your data.

- Otherwise you can fallback to using the `RecordSet::setConversionInputEncoding` method.

<p class="message-warning">If your CSV is not <code>UTF-8</code> encoded some unexpected results or errors may be thrown when trying to convert your data.</p>

~~~php
<?php

public RecordSet::jsonSerialize(): array

public RecordSet::toXML(
    string $root_name = 'csv',
    string $row_name = 'row',
    string $cell_name = 'cell',
    string $column_attr = 'name',
    string $offset_attr = 'offset',
): DOMDocument

public RecordSet::toHTML(
    string $class_attr = 'table-csv-data',
    string $column_attr = 'title',
    string $offset_attr = 'data-record-offset'
): string
~~~

- `RecordSet` implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object.
- `RecordSet::toXML` converts the `RecordSet` into a `DomDocument` object.
- `RecordSet::toHTML` converts the `RecordSet` into an HTML table.

The `RecordSet::toXML` method accepts five (5) optionals arguments to help you customize your XML tree:

- `$root_name`, the XML root name which defaults to `csv`;
- `$row_name`, the XML node element representing a CSV row which defaults to `row`;
- `$cell_name`, the XML node element for each CSV cell which defaults value is `cell`;
- `$column_attr`, the XML node element attribute for each CSV cell if a header was prodived which defaults value is `name`;
- `$offset_attr`, the XML node element attribute for each CSV record if the offset must be preserved which defaults value is `offset`;


The `RecordSet::toHTML` method accepts three (3) optional arguments:

- `$class_attr` to help you customize the table rendering. By defaut the classname given to the table is `table-csv-data`.
- `$column_attr`, the attribute attach to each `<td>` to indicate the column name if it is provided. The default value is `title`;
- `$offset_attr`, the attribute attach to each `<tr>` to indicate the CSV document original offset index. The default value is `data-record-offset`

<p class="message-notice">The <code>$column_attr</code> argument from <code>RecordSet::toXML</code> and <code>RecordSet::toHTML</code> will only appear if the <code>RecordSet::getColumnNames</code> returns an non empty <code>array</code>.</p>

<p class="message-notice">The <code>$offset_attr</code> argument from <code>RecordSet::toXML</code> and <code>RecordSet::toHTML</code> will only appear in the converted document if the <code>RecordSet::preserveOffset</code> status is <code>true</code>.</p>

~~~php
<?php

use League\Csv\Statement;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/prenoms.csv')
    ->setDelimiter(';')
    ->setHeaderOffset(0)
    ->addStreamFilter('convert.iconv.ISO-8859-1/UTF-8')
;

$stmt = (new Statement())
    ->where(function (array $record) {
        return 'Anaïs' === $record['prenoms'];
    })
    ->offset(0)
    ->limit(2)
;

$records = $csv->select($stmt);
$records->preserveOffset(true);
$dom = $records->toXML('csv', 'record', 'field');
$dom->formatOutput = true;
echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="UTF-8"?>
// <csv>
//   <record offset="71">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">137</field>
//     <field name="sexe">F</field>
//     <field name="annee">2004</field>
//   </record>
//   <record offset="1099">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">124</field>
//     <field name="sexe">F</field>
//     <field name="annee">2005</field>
//   </record>
// </csv>
~~~
