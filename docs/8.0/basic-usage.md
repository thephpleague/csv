---
layout: default
title: Basic Usage
redirect_from: /basic-usage/
---

# Basic usage

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>

Once your CSV object is [instantiated](/8.0/instantiation) and [configured](/8.0/properties/), you can start interacting with the data using a number of methods available to you.

## Iterating over the CSV rows

The CSV object implements PHP's `IteratorAggregate` interface

```php
public AbstractCsv::getIterator(void): Iterator
```

You can iterate over your CSV object to extract each CSV row using the `foreach` construct.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
foreach ($reader as $index => $row) {
    //do something meaningful here with $row !!
    //$row is an array where each item represent a CSV data cell
    //$index is the CSV row index
}
```

<p class="message-notice">You can do more complex iterations <a href="/8.0/reading/">using the query methods</a> available on the <code>League\Csv\Reader</code> class only.</p>

## Outputting the CSV

### __toString

Returns the string representation of the CSV document

```php
public AbstractCsv::__toString(void): string
```

Use the `echo` construct on the instantiated object or use the `__toString` method to show the CSV full content.

#### Example

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
echo $reader;
// or
echo $reader->__toString();
```

### output

If you only wish to make your CSV downloadable by forcing a file download just use the `output` method to force the use of the output buffer on the CSV content.

```php
public AbstractCsv::output(string $filename = null): int
```

- The method returns the number of characters read from the handle and passed through to the output.
- The output method can take an optional argument `$filename`. When present you
can even remove more headers.

#### Example 1 - default usage

```php
use League\Csv\Reader;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->output();
die;
```

#### Example 2 - using the $filename argument

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->output("name-for-your-file.csv");
die;
```

#### Example 3 - using a Response object (Symfony, Laravel, PSR-7 etc)

To avoid breaking the flow of your application, you should create a Response object when applicable in your framework. The actual implementation will differ per framework, but you should generally not output headers directly.
In some cases you can also use a Streaming Response for larger files.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
return new Response((string) $reader, 200, [
    'Content-Type' => 'text/csv; charset=UTF-8',
    'Content-Disposition' => 'attachment; filename="name-for-your-file.csv"',
]);
```

#### Notes

The output methods **can only be affected by:**

- the [library stream filtering mechanism](/8.0/filtering/)
- the [BOM property](/8.0/bom/)

No other method or property have effect on them.
