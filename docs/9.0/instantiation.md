---
layout: default
title: Loading CSV documents
---

# Instantiation

## OS support

If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
<?php

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continues here...
~~~

## Connection type

Accessing the CSV document is done using one of the following class:

* `League\Csv\Reader` to connect on a read only mode
* `League\Csv\Writer` to connect on a write only mode

Both classes extend the `League\Csv\AbstractCsv` class and as such share methods for instantiation.

## CSV document loading

~~~php
<?php

public static AbstractCsv::createFromString(string $str): AbstractCsv
public static AbstractCsv::createFromPath(string $path, string $open_mode = 'r+'): AbstractCsv
public static AbstractCsv::createFromStream(resource $stream): AbstractCsv
public static AbstractCsv::createFromFileObject(SplFileObject $obj): AbstractCsv
~~~

Because CSV documents come in different forms we used named constructors to offer several ways to load them.

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