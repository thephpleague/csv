---
layout: default
title: Force Enclosure
---

# Force field enclosure

<p class="message-info">Available since <code>version 9.1.0</code></p>

The `EncloseField` is a PHP stream filter which forces the `Writer` class to enclose all its record fields.

<p class="message-warning">Changing the CSV objects control characters <strong>after registering the stream filter</strong> may result in unexpected returned records.</p>

## Usage with Writer objects

```php
public static EncloseField::addTo(Writer $csv, string $sequence): Writer
```

The `EncloseField::addTo` method will:

- register the stream filter if it is not already the case
- add a formatter to the `Writer` object to force `fputcsv` to enclose all record field
- add a stream filter to the `Writer` object to remove the added sequence from the final CSV.

```php
use League\Csv\EncloseField;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp');
EncloseField::addTo($writer, "\t\x1f"); //adding the stream filter to force enclosure
$writer->insertAll($iterable_data);
$writer->output('mycsvfile.csv'); //outputting a CSV Document with all its field enclosed
```

<p class="message-warning">The <code>$sequence</code> argument should be a sequence containing at least one character that forces <code>fputcsv</code> to enclose the field value. If not, an <code>InvalidArgumentException</code> exception will be thrown.</p>

## Usage with PHP stream resources

```php
public static EncloseField::register(): void
public static EncloseField::getFiltername(): string
```

To use this stream filter outside `League\Csv` objects you need to:

- register the stream filter using `EncloseField::register` method.
- use `EncloseField::getFiltername` with one of PHP's attaching stream filter functions with the correct arguments as shown below:

```php
use League\Csv\EncloseField;

EncloseField::register();

$sequence = "\t\x1f";

$resource = fopen('/path/to/my/file', 'r+');
$filter = stream_filter_append($resource, EncloseField::getFiltername(), STREAM_FILTER_WRITE, [
    'sequence' => $sequence,
]);

$record = array_map(function ($value) use ($sequence) {
    return $sequence.$value;
}, $record);

fputcsv($resource, $record);
```
