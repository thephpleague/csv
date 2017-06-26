---
layout: default
title: CSV document output
---

# CSV document output

Once your CSV document is loaded, you can print or enable downloading it using the methods below.

The output methods **are affected by** [the output BOM sequence](/9.0/connections/bom/) and/or the supplied [PHP stream filters](/9.0/connections/filters/).

<p class="message-info">Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>


## Printing the document

Returns the string representation of the CSV document

~~~php
<?php

public AbstractCsv::__toString(void): string
~~~

Use the `echo` construct on the instantiated object or use the `__toString` method to return the CSV full content.

### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
echo $reader;
// or
echo $reader->__toString();
~~~

## Downloading the document

To make your CSV document downloadable use the `output` method to force the use of the output buffer on the CSV content.

~~~php
<?php

public AbstractCsv::output(string $filename = null): int
~~~

The method returns the number of characters read from the handle and passed through to the output.

The output method can take an optional argument `$filename`. When present you
can even remove more headers.

### Default usage

~~~php
<?php

use League\Csv\Reader;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output();
die;
~~~

### Using the $filename argument

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output("name-for-your-file.csv");
die;
~~~

## Outputting the document into chunks

~~~php
<?php

public AbstractCsv::chunk(int $length): Generator
~~~

The `AbstractCsv::chunk` method takes a single `$length` parameter specifying the number of bytes to read from the CSV document and returns a `Generator` to ease outputting large CSV files.

<p class="message-warning">if the <code>$length</code> parameter is not a positive integer a <code>OutOfRangeException</code> will be thrown.</p>

~~~php
<?php

use League\Csv\Reader;

header('Transfer-Encoding: chunked');
header('Content-Encoding: none');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/huge/file.csv', 'r');
foreach ($reader->chunk(1024) as $chunk) {
    echo dechex(strlen($chunk))."\r\n$chunk\r\n";
}
echo "0\r\n\r\n";
~~~

<p class="message-info">If your application is using a framework, to avoid breaking its flow, you should create a framework specific <code>Response</code> object when applicable using <code>AbstractCsv::__toString</code> or <code>AbstractCsv::chunk</code> depending on the size of your CSV document.</p>
