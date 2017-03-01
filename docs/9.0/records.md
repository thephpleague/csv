---
layout: default
title: Accessing Records from a CSV document
---

# Records Collection

The `League\Csv\RecordSet` is a class which manipulates the CSV document records. This object is returned from `Reader::select` or `Statement::process` execution.

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

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->select();
count($results); //return the total number of records found
~~~

`RecordSet::getColumnNames` returns the columns name information associated with the current object. This is usefull if the `RecordSet` object was created from:

- a `Reader` object where `Reader::getHeader` is not empty;
- and/or a `Statement` object where `Statement::columns` was used.

### Example: no header information was given

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$records = $reader->select();
$records->getColumnNames(); // is empty because no header information was given
~~~

### Example: header information given by the Reader object

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->select();
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
]);
$records = $reader->select();
$records->getColumnNames(); // returns ['firstname', 'lastname', 'email'];
~~~

## Collection options

~~~php
<?php

public RecordSet::preserveOffset(bool $status): RecordSet
public RecordSet::setConversionInputEncoding(string $charset): RecordSet
~~~

`RecordSet::preserveOffset` indicates if the `RecordSet` must keep the original CSV document records offset or can re-index them. When the `$status` is `true`, the original CSV document record offset will be preserve and output in methods where it makes sense.

<p class="message-notice">By default, the <code>RecordSet</code> object does not preserve the original offset.</p>

`RecordSet::setConversionInputEncoding` performs a charset conversion so that the records are all in `UTF-8` prior to converting the collection into XML or JSON. Without this step, errors may occurs while converting your data.

<p class="message-notice">By default, the <code>RecordSet</code> object expect your records to be in <code>UTF-8</code>.</p>

<p class="message-info"><strong>Tips:</strong> if the <code>Reader</code> supports stream filtering, use <code>Reader::addStreamFilter</code> instead to perform this charset conversion.</p>

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

$reader = Reader::createFromPath('/path/to/my/file.csv');
$results = $reader->select();
foreach ($results as $offset => $record) {
    //do something here
}

foreach ($results->fetchAll() as $offset => $record) {
    //do something here
}

~~~

If the `RecordSet::preserveOffset` is set to `true`, the `$offset` parameter will contains the original CSV document offset index, otherwise it will contain a numerical index starting from `0`.

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
        'First Name' => 'firstname',
        'Last Name' => 'lastname',
        'E-mail' => 'email',
    ])
;
$records = $reader->select();
$reader->getColumnNames(); //returns ['firstname', 'lastname', 'email']
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

public RecordSet::fetchOne($offset = 0): array
~~~

The required argument `$offset` represents the record offset in the record collection starting at `0`. If no argument is given the method will return the first record from the result set. If no record is found an empty `array` is returned.

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
$result = $records->fetchColumn('First Name');
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

## Selecting key-value pairs

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

### Notes

- If no `$offsetIndex` is provided it default to `0`;
- If no `$valueIndex` is provided it default to `1`;
- If no cell is found corresponding to `$offsetIndex` the row is skipped;
- If no cell is found corresponding to `$valueIndex` the `null` value is used;

## Converting the collection into JSON/XML/HTML

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
