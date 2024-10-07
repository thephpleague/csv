---
layout: default
title: Accessing Records from a CSV document
---

# Result Set

A `League\Csv\ResultSet` object represents the associated result set of processing a [CSV document](/9.0/reader/) with a [constraint builder](/9.0/reader/statement/).
This object is returned from [Statement::process](/9.0/reader/statement/#apply-the-constraints-to-a-csv-document) execution.

<p class="message-info">Starting with version <code>9.6.0</code>, the class implements the <code>League\Csv\TabularDataReader</code> interface.</p>

## Selecting records

Please header over the [TabularDataReader documentation page](/9.0/reader/tabular-data-reader)
for more information on the class features. If you require a more advance record selection, you
should use a [Statement or a FragmentFinder](/9.0/reader/statement/) class to process the `Reader` object. The
found records are returned as a [ResultSet](/9.0/reader/resultset) object.

## Conversions

### Json serialization

<p class="message-info">A dedicated <code>JsonConverter</code> class is added in version <code>9.17.0</code>
to help <a href="/9.0/converter/json/">converting ResultSet into proper JSON document</a> without consuming
too much memory. It is the recommended way to convert to JSON.</p>

The `ResultSet` class implements the `JsonSerializable` interface. As such you can use the `json_encode`
function directly on the instantiated object. The interface is implemented  using PHP's `iterator_array`
on the `ResultSet::getRecords` method. As such, the returned `JSON` string data is affected by the
presence or absence of column names.

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
