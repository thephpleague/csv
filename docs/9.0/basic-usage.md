---
layout: default
title: Basic Usage
---

# Basic usage

## Csv and Macintosh

If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
<?php

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continues here...
~~~

## Instantiation

Accessing the CSV document is done using one of the following class:

* `League\Csv\Reader` to connect on a read only mode
* `League\Csv\Writer` to connect on a write only mode

Both classes extend the `League\Csv\AbstractCsv` class and as such share methods for instantiation.

Because CSV documents come in different forms we used named constructors to offer several ways to load them.

~~~php
<?php

public static AbstractCsv::createFromString(string $str): AbstractCsv
public static AbstractCsv::createFromPath(string $path, string $open_mode = 'r+'): AbstractCsv
public static AbstractCsv::createFromStream(resource $stream): AbstractCsv
public static AbstractCsv::createFromFileObject(SplFileObject $obj): AbstractCsv
~~~

### Create from a string

~~~php
<?php

public static AbstractCsv::createFromString(string $str): AbstractCsv
~~~

Creates a new object from a given string.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
~~~

### Create from a file path

~~~php
<?php

public static AbstractCsv::createFromPath(string $path, string $open_mode = 'r+'): AbstractCsv
~~~

Creates a new object *à la* `fopen`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;


$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
//the $reader object will use the 'r+' open mode.
$writer = Writer::createFromPath('/path/to/your/csv/file.csv', 'w');
~~~

<p class="message-notice"> The <code>$open_mode</code> default to <code>r+</code> if none is supplied.</p>

### Create from a resource stream

~~~php
<?php

public static AbstractCsv::createFromStream(resource $stream): AbstractCsv
~~~

Creates a new object from a stream resource.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromStream(fopen('/path/to/the/file.csv', 'r+'));
$writer = Writer::createFromStream(tmpfile());
~~~

<p class="message-warning"> The resource stream <strong>MUST</strong> be seekable otherwise a <code>InvalidArgumentException</code> is thrown.</p>

### Create from a SPL file object

~~~php
<?php

public static AbstractCsv::createFromFileObject(SplFileObject $obj): AbstractCsv
~~~

Creates a new object from a `SplFileObject` object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject());
~~~

## CSV document output

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>

Once your CSV document is loaded, you can print or enable downloading the CSV document using the methods below.

~~~php
<?php

public AbstractCsv::__toString(void): string
public AbstractCsv::output(string $filename = null): int
~~~

### __toString

Returns the string representation of the CSV document

~~~php
<?php

public AbstractCsv::__toString(void): string
~~~

Use the `echo` construct on the instantiated object or use the `__toString` method to show the CSV full content.

#### Example

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
echo $reader;
// or
echo $reader->__toString();
~~~

### Download the CSV

If you only wish to make your CSV document downloadable use the `output` method to force the use of the output buffer on the CSV content.

~~~php
<?php

public AbstractCsv::output(string $filename = null): int
~~~

The method returns the number of characters read from the handle and passed through to the output.

The output method can take an optional argument `$filename`. When present you
can even remove more headers.

#### Example 1 - default usage

~~~php
<?php

use League\Csv\Reader;

header('content-type: text/csv; charset=UTF-8');
header('content-disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output();
~~~

#### Example 2 - using the $filename argument

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->output("name-for-your-file.csv");
~~~

<p class="message-info"><strong>Tips:</strong> The methods output <strong>are affected by</strong> <a href="/9.0/attributes/">the connection attributes</a>.</p>
