---
layout: default
title: CSV document Writer connection
---

# Writer connection

~~~php
<?php

class Writer extends AbstractCsv
{
    public function addFormatter(callable $callable): Writer
    public function addValidator(callable $callable, string $validatorName): Writer
    public function getFlushThreshold(): int|null
    public function getNewline(): string
    public function insertAll(iterable $records): int
    public function insertOne(array $record): int
    public function setFlushThreshold(?int $threshold): self
    public function setNewline(string $sequence): self
}
~~~

The `League\Csv\Writer` class extends the general connections [capabilities](/9.0/connections/) to create or update a CSV document.

<p class="message-warning">When inserting records into a CSV document using <code>League\Csv\Writer</code>, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your CSV document before insertion, you may change the file cursor position and/or erased your data.</p>

## Inserting records

~~~php
<?php

public Writer::insertOne(array $record): int
public Writer::insertAll(iterable $records): int
~~~

`Writer::insertOne` inserts a single record into the CSV document while `Writer::insertAll` adds several records. Both methods returns the length of the written data or throw and `League\Csv\Exception\InsertionException` on error.

`Writer::insertOne` takes a single argument, an `array` which represents a single CSV record.
`Writer::insertAll` takes a single argument a PHP iterable which contains a collection of CSV records.

~~~php
<?php

use League\Csv\Writer;

$records = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

$writer = Writer::createFromPath('/path/to/saved/file.csv', 'w+');
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertAll($records); //using an array
$writer->insertAll(new ArrayIterator($records)); //using a Traversable object
~~~

In the above example, all CSV records are saved to `/path/to/saved/file.csv`

## Handling newline

Because PHP's `fputcsv` implementation has a hardcoded `\n`, we need to be able to replace the last `LF` code with one supplied by the developper for more interoperability between CSV packages on different platforms. The newline sequence will be appended to each newly inserted CSV record.

### Description

~~~php
<?php

public Writer::setNewline(string $sequence): self
public Writer::getNewline(void): string
~~~

### Example

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplFileObject());
$newline = $writer->getNewline(); // equals "\n";
$writer->setNewline("\r\n");
$newline = $writer->getNewline(); // equals "\r\n";
$writer->insertOne(["one", "two"]);
echo $writer; // displays "one,two\r\n";
~~~

<p class="message-info">The default newline sequence is <code>\n</code>;</p>

## Flushing the buffer

For advanced usages, you can now manually indicates when flushing mechanism occurs while adding new content to your CSV document.

### Description

~~~php
<?php

public Writer::setFlushThreshold(?int $treshold): self
public Writer::getFlushThreshold(void): int|null
~~~

By default, `getFlushTreshold` returns `null`.

<p class="message-info"><code>Writer::insertAll</code> always flush its buffer when all records are inserted regardless of the threshold value.</p>

<p class="message-info">If set to <code>null</code> the inner flush mechanism of <code>SplFileObject::fputcsv</code> or <code>fputcsv</code> will be used.</p>


## Records filtering

~~~php
<?php

public Writer::addFormatter(callable $callable): Writer
public Writer::addValidator(callable $callable, string $validatorName): Writer
~~~

Sometimes you may want to format and/or validate your records prior to their insertion into your CSV document. the `Writer` class provides a formatter and a validator mechanism to ease these operations.

### Record Formatter

A formatter is a `callable` which accepts an `array` on input and returns a formatted array according to its inner rules.

~~~php
<?php

function(array $record): array
~~~

You can attach as many formatters as you want to the `Writer` class to manipulate your data prior to its insertion use the `Writer::addFormatter` method. The formatters follow the *First In First Out* rule when applied.

~~~php
<?php

use League\Csv\Writer;

$formatter = function (array $row): array {
    return array_map('strtoupper', $row);
};
$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->addFormatter($formatter);
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

$writer->__toString();
//will display something like JOHN,DOE,JOHN.DOE@EXAMPLE.COM
~~~

### Record Validator

A validator is a `callable` which takes an `array` as its sole argument and returns a boolean.

~~~php
<?php

function(array $record): bool
~~~

The validator **must** return `true` to validate the submitted record.

Any other expression, including thruthy ones like `yes`, `1`,... will make the `insertOne` method throw an `League\Csv\Exception\InsertionException`.

As with the formatter capabilities, you can attach as many validators as you want to your data prior to its insertion using the `Writer::addValidator` method. The record data is checked against your supplied validators **after it has been formatted**.

`Writer::addValidator` takes two parameters:

- A `callable` which takes an `array` representing a Csv record as its unique parameter;
- The validator name which is **required**. If another validator was already registered with the given name, it will be overriden.

On failure a [League\Csv\Exception\InsertionException](/9.0/connections/exceptions/#runtime-exceptions) exception is thrown by the `Writer` object.

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Exception\InsertionException;

$writer->addValidator(function (array $row): bool {
    return 10 == count($row);
}, 'row_must_contain_10_cells');
try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (InsertionException $e) {
    echo $e->getName(); //display 'row_must_contain_10_cells'
    $e->getData();//will return the invalid data ['john', 'doe', 'john.doe@example.com']
}
~~~

