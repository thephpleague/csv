---
layout: default
title: CSV document Writer connection
---

# Writer connection

~~~php
<?php

public Writer::getNewline(void): string
public Writer::getFlushThreshold(void): int|null
public Writer::insertOne(array $record): int
public Writer::insertAll(iterable $records): int
public Writer::setNewline(string $sequence): self
public Writer::setFlushThreshold(?int $threshold): self
~~~

The `League\Csv\Writer` class extends the general connections [capabilities](/9.0/connections/) to create or update a CSV document.

<p class="message-info"><strong>Tips: </strong> When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

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

$rows = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertAll($rows); //using an array
$writer->insertAll(new ArrayIterator($rows)); //using a Traversable object
~~~

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


