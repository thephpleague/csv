---
layout: default
title: CSV document Reader connection
---

# Reader Connection

The `League\Csv\Reader` class extends the general connections [capabilities](/9.0/connections/) to ease selecting
and manipulating CSV document records. Starting with version `9.6.0`, the class implements
the `League\Csv\TabularDataReader` interface.

<p class="message-notice">Starting with version <code>9.1.0</code>, <code>createFromPath</code> has its default <code>open_mode</code> parameter set to <code>r</code>.</p>
<p class="message-notice">Prior to <code>9.1.0</code>, the open mode was <code>r+</code> which looks for write permissions on the file and throws an <code>Exception</code> if the file cannot be opened with the permission set. For sake of clarity, it is strongly suggested to set <code>r</code> mode on the file to ensure it can be opened.</p>

The `Reader` provides a convenient and straight forward API to access and handle CSV. While most
of its capabilities are explained in the [Tabular Data Reader documentation page](/9.0/reader/tabular-data-reader),
the current page will focus on `Reader` specific features and/or properties.

## CSV example

Many examples in this reference require a CSV file. We will use the following file `file.csv`
containing the following data:

```csv
"First Name","Last Name",E-mail
john,doe,john.doe@example.com
jane,doe,jane.doe@example.com
john,john,john.john@example.com
jane,jane
```

## Records normalization

### General Rules

The returned records are normalized using the following rules:

- [Stream filters](/9.0/connections/filters/) are applied if present.
- Empty records are skipped if present.
- The document BOM sequence is skipped if present.
- If a header record was provided, the number of fields is normalized to the number of fields contained in that record:
  - Extra fields are truncated.
  - Missing fields are added with a `null` value.
- Field values are formatter if formatters are provided **Since version 9.11**

```php
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
}
```

### Record Formatter

<p class="message-info">New since version <code>9.11.0</code></p>

A formatter is a `callable` which accepts a single CSV record as an `array` on input and returns an array
representing the formatted CSV record according to its inner rules.

```php
function(array $record): array
```

You can attach as many formatters as you want to the `Reader` class using the `Reader::addFormatter` method.
Formatters are applied following the *First In First Out* rule.

Formatting happens **AFTER** combining the header and the fields value if a header is available and
CSV value **BUT BEFORE<** you can access the actual value.

```php
use League\Csv\Reader;

$csv = <<<CSV
firstname,lastname,e-mail
john,doe,john.doe@example.com
CSV;

$formatter = fn (array $row): array => array_map(strtoupper(...), $row);
$reader = Reader::createFromString($csv)
    ->setHeaderOffset(0)
    ->addFormatter($formatter);
[...$reader]; 
// [
//     [
//         'firstname' => 'JOHN',
//         'lastname' => DOE',
//         'e-mail' => 'JOHN.DOE@EXAMPLE.COM',
//     ],
//];

echo $reader->toString(); //returns the original $csv value without the formatting.
```

<p class="message-warning">If a header is selected it won't be affected by the formatting</p>
<p class="message-warning">Formatting does not affect the CSV document content.</p>

### Controlling the presence of empty records

<p class="message-info">New since version <code>9.4.0</code></p>

By default, the CSV document normalization removes empty records, but you can control the presence of
such records using the following methods:

```php
Reader::skipEmptyRecords(): self;
Reader::includeEmptyRecords(): self;
Reader::isEmptyRecordsIncluded(): bool;
```

- Calling `Reader::includeEmptyRecords` will ensure empty records are left in the `Iterator` returned by
  `Reader::getRecords`, conversely `Reader::skipEmptyRecords` will ensure empty records are skipped.
- At any given time you can ask your Reader instance if empty records will be stripped or
  included using the `Reader::isEmptyRecordsIncluded` method.
- If no header offset is specified, the empty record will be represented by an empty `array`.
  Conversely, for consistency, an empty record will be represented by an array filled
  with `null` values as expected from header presence normalization.

<p class="message-notice">The record offset is always independent of the presence of empty records.</p>

```php
use League\Csv\Reader;

$source = <<<EOF
"parent name","child name","title"


"parentA","childA","titleA"
EOF;

$reader = Reader::createFromString($source);
$reader->isEmptyRecordsIncluded(); //returns false
iterator_to_array($reader, true);
// [
//     0 => ['parent name', 'child name', 'title'],
//     3 => ['parentA', 'childA', 'titleA'],
// ];

$reader->includeEmptyRecords();
$reader->isEmptyRecordsIncluded(); //returns true
iterator_to_array($reader, true);
// [
//     0 => ['parent name', 'child name', 'title'],
//     1 => [],
//     2 => [],
//     3 => ['parentA', 'childA', 'titleA'],
// ];

$reader->setHeaderOffset(0);
iterator_to_array($reader, true);
// [
//     1 => ['parent name' => null, 'child name' => null, 'title' => null],
//     2 => ['parent name' => null, 'child name' => null, 'title' => null],
//     3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
// ];

$reader->skipEmptyRecords();
$reader->isEmptyRecordsIncluded(); //returns false
$res = iterator_to_array($reader, true);
// [
//     3 => ['parent name' => 'parentA', 'child name' => 'childA', 'title' => 'titleA'],
// ];
```

## Document header

While accessing the CSV header is done via the `getHeader` method which is part of the `TabularDataReader` API,
Because CSV documents come in difference shape and form the class exposes a way to select and get the document Header
record via the `setHeaderOffset` and `getHeaderOffset` method.

### Description

```php
public Reader::setHeaderOffset(?int $offset): self
public Reader::getHeaderOffset(void): ?int
public Reader::getHeader(void): array
```

### Example

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
```

If no header offset is set:

- `Reader::getHeader` method will return an empty array.
- `Reader::getHeaderOffset` will return `null`.

<p class="message-info">By default no header offset is set.</p>
<p class="message-warning">Because the header is lazy loaded, if you provide a positive offset
for an invalid record a <code>SyntaxError</code> exception will be triggered when trying
to access the invalid record.</p>

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
$header_offset = $csv->getHeaderOffset(); //returns 1000
$header = $csv->getHeader(); //throws a SyntaxError exception
```

Because the CSV document is treated as tabular data the header can not contain duplicate entries.
If the header contains duplicates an exception will be thrown on usage.

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->nth(0); //returns ['field1', 'field2', 'field1', 'field4']
$csv->setHeaderOffset(0); //valid offset but the record contain duplicates
$header_offset = $csv->getHeaderOffset(); //returns 0
$records = $csv->getRecords(); //throws a SyntaxError exception
```

<p class="message-info">Starting with <code>9.7.0</code> the <code>SyntaxError</code> exception thrown
will return the list of duplicate column names.</p>

```php
use League\Csv\Reader;
use League\Csv\SyntaxError;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->nth(0); //returns ['field1', 'field2', 'field1', 'field4']
$csv->setHeaderOffset(0); //valid offset but the record contain duplicates
$header_offset = $csv->getHeaderOffset(); //returns 0
try {
    $records = $csv->getRecords(); //throws a SyntaxError exception
} catch (SyntaxError $exception) {
    $duplicates = $exception->duplicateColumnNames(); //returns ['field1']
}
```

## Document records

To access the CSV records you will need to use the `getRecords` or the `getRecordsAsObjects` methods. The methods
returns an `Iterator` containing all CSV document records as `array` or as objects. It will extract the
records using the [CSV controls characters](/9.0/connections/controls/).

<p class="message-notice"><code>getRecords</code> and <code>getRecordsAsObjects</code> are part of the <code>TabularDataReader</code> API.</p>

```php
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
}
```

### Records selection with Reader::setHeaderOffset

Just like the `getHeader` method, the method output depends on the header record selected using `setHeaderOffset`.

```php
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
}
```

<p class="message-notice">The optional <code>$header</code> argument from the <code>Reader::getRecords</code>
takes precedence over the header offset property but its corresponding record will still be removed
from the returned <code>Iterator</code>.</p>

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record) returns something like
    // array(
    //  'firstname' => 'jane',
    //  'lastname' => 'doe',
    //  'email' => 'jane.doe@example.com'
    // );
}
//the first record will still be skipped!!
```

## Selecting records

Please header over the [TabularDataReader documentation page](/9.0/reader/tabular-data-reader)
for more information on the class features. If you require a more advance record selection, you
should use a [Statement or a FragmentFinder](/9.0/reader/statement/) class to process the `Reader` object. The
found records are returned as a [ResultSet](/9.0/reader/resultset) object.

## Records conversion

### Json serialization

<p class="message-info">A dedicated <code>JsonConverter</code> class is added in version <code>9.17.0</code>
to help <a href="/9.0/converter/json/">converting CSV into proper JSON document</a> without consuming
too much memory. It is the recommended way to convert to JSON.</p>

The `Reader` class implements the `JsonSerializable` interface. As such you can use the `json_encode`
function directly on the instantiated object. The interface is implemented using PHP's
`iterator_array` on the `Reader::getRecords` method. As such, the returned `JSON`
string data depends on the presence or absence of a header.

```php
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
```

<p class="message-notice">The record offset <strong>is not preserved on conversion</strong></p>

<p class="message-notice">To convert your CSV to <code>JSON</code> you must be sure its content
is <code>UTF-8</code> encoded, using, for instance, the library
<a href="/9.0/converter/charset/">CharsetConverter</a> stream filter.</p>

### Other conversions

If you wish to convert your CSV document in `XML` or `HTML` please refer to the [converters](/9.0/converter/) bundled
with this library.
