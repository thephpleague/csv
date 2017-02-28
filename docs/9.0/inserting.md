---
layout: default
title: Inserting new data into a CSV
---

# Inserting Records

To create or update a CSV you are required to use the `League\Csv\Writer` connection.

~~~php
<?php

public Writer::insertOne(array $record): int
public Writer::insertAll(iterable $records): int
public Writer::getFlushTreshold(void): int|null
public Writer::getNewline(void): string
public Writer::addFormatter(callable $callable): Writer
public Writer::addValidator(callable $callable, string $validatorName): Writer
public Writer::setFlushTreshold(int $treshold = null): Writer
public Writer::setNewline(string $sequence): Writer
~~~

The `Writer` class exposes the general connections <a href="/9.0/connections/">methods</a>  and <a href="/9.0/attributes/">attributes</a>.

<p class="message-info"><strong>Tips: </strong> When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## Adding new data

The `Writer` class performs a number of actions while inserting your data into the CSV. When submitting data for insertion the class will proceed as describe below for each row.

It will:

- format the given record if formatters are supplied;
- validate the formatted record if validators are supplied;
- update the newline sequence if needed;
- apply the PHP stream filters if they were configured;

To add new data to your CSV the `Writer` class uses the following methods

### Writer::insertOne

`insertOne` inserts a single record.

~~~php
<?php

public Writer::insertOne(array $record): int
~~~

This method takes a single argument `$record`, an `array` and returns the length of the written string or throw and `League\Csv\Exception\InsertionException` on error.

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
~~~

### Writer::insertAll

`insertAll` inserts multiple records.

~~~php
<?php

public Writer::insertAll(iterable $records): int
~~~

This method adds several records to the CSV data and returns the length of the written data or throw and `League\Csv\Exception\InsertionException` on error.

This method takes a single argument `$records` which is a `iterable` containing simple records.

~~~php
<?php

use League\Csv\Writer;

$rows = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->insertAll($rows); //using an array
$writer->insertAll(new ArrayIterator($rows)); //using a Traversable object
~~~

## Record manipulations

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

public Writer::addFormatter(callable $callable): Writer
~~~

#### Example

~~~php
<?php

use League\Csv\Writer;

$formatter = function ($row) {
    return array_map('strtoupper', $row);
};
$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->addFormatter($formatter);
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

$writer->__toString();
//will display something like JOHN,DOE,JOHN.DOE@EXAMPLE.COM
~~~

### Record validator

A validator is a `callable` which takes an `array` as its sole argument and returns a boolean.

~~~php
<?php

function(array $record): bool
~~~

The validator **must** return `true` to validate the submitted record.

Any other expression, including thruthy ones like `yes`, `1`,... will make the `insertOne` method throw an `League\Csv\Exception\InsertionException`.

As with the formatter capabilities, you can attach as many validators as you want to your data prior to its insertion using the `Writer::addValidator` method. The record data is checked against your supplied validators **after it has been formatted**.

~~~php
<?php

public Writer::addValidator(callable $callable, string $validatorName): Writer
~~~

`Writer::addValidator` takes two parameters:

- A `callable` which takes an `array` representing a Csv record as its unique parameter;
- The validator name which is **required**. If another validator was already registered with the given name, it will be overriden.

On failure a `League\Csv\Exception\InsertionException`  exception is thrown by the `Writer` object.
This exception extends PHP's `RuntimeException` by adding two public getter methods:

~~~php
<?php

public InsertionException::getName(void): string
public InsertionException::getData(void): array
~~~

- `InsertionException::getName`: returns the name of the failed validator
- `InsertionException::getData`: returns the invalid data submitted to the validator

#### Example

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Exception\InsertionException;

$writer->addValidator(function (array $row) {
    return 10 == count($row);
}, 'row_must_contain_10_cells');
try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (InsertionException $e) {
    echo $e->getName(); //display 'row_must_contain_10_cells'
    $e->getData();//will return the invalid data ['john', 'doe', 'john.doe@example.com']
}
~~~

## Handling newline

Because PHP's `fputcsv` implementation has a hardcoded `\n`, we need to be able to replace the last `LF` code with one supplied by the developper for more interoperability between CSV packages on different platforms. The newline sequence will be appended to each newly inserted CSV record.

#### Description

~~~php
<?php

public Writer::setNewline(string $sequence): Writer
public Writer::getNewline(void): string
~~~

#### Example

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

## Flush Threshold

To improve records insertion, you can now manually indicates when flushing mechanism occurs while adding new content to your CSV document.

#### Description

~~~php
<?php

public Writer::setFlushTreshold(int $treshold = null): Writer
public Writer::getFlushTreshold(void): int|null
~~~

<p class="message-info">By default, the flush mechanism is activate every 500 insertions.</p>

<p class="message-info"><code>Writer::insertAll</code> always flush its buffer when all insertion are done regardless of the threshold.</p>

<p class="message-info">if set to <code>null</code> the inner flush mechanism of <code>SplFileObject::fputcsv</code> or <code>fputcsv</code> will be used.</p>


