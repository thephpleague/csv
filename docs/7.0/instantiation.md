---
layout: default
title: Instantiation using named constructors
---

# Instantiation

The library is composed of two main classes:

* `League\Csv\Reader` to read data from a CSV
* `League\Csv\Writer` to write new data into a CSV

Both classes extend the `League\Csv\AbstractCsv` class and as such share methods for instantiation.

## Mac OS Server

**If you are on a Mac OS X Server**, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
<?php

if (! ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continues here...
~~~

## Instantiating a new CSV object

Because CSVs come in different forms we used named constructors to offer several ways to instantiate the library objects.

### createFromPath($path, $open_mode)

This named constructor will create a new object *à la* `fopen`:

* The `$path` parameter can be:
    * a `SplFileInfo` object, the string path will be fetch from the object public methods.
    * an object implementing the `__toString` method the path will be the object string representation.
    * a string.

<p class="message-warning"><strong>Warning:</strong> The method throws an <code>InvalidArgumentException</code> if a <code>SplTempFileObject</code> is given as no path can be retrieve from such object.</p>
* The `$open_mode` parameter which defaults to `r+` if none is supplied.

The resulting string and `$open_mode` parameters are used to lazy load internally a `SplFileObject` object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
//the $reader object will use the 'r+' open mode as no `open_mode` parameter was supplied.
$writer = Writer::createFromPath(new SplFileObject('/path/to/your/csv/file.csv', 'a+'), 'w');
//the $writer object open mode will be 'w'!!
~~~

### createFromFileObject(SplFileObject $obj)

If you have a `SplFileObject` and you want to directly work with it you should use the `createFromFileObject` named constructor. This method accepts only one single parameter, a `SplFileObject` object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject());

~~~

### createFromString($str, $newline = "\n")

<p class="message-notice">The <code>$newline</code> argument was added in version 7.0</p>

If you have a raw CSV string use the `createFromString` named constructor. This method accepts two parameters:

- the raw CSV string;
- the newline sequence to be added at the end of the raw CSV string;

If no newline sequence is specified, the newline sequence used will be `\n` to match the one added by PHP `fputcsv` function.

<p class="message-warning">The <code>$newline</code> argument is deprecated since version 7.2 and will be removed in the next major release.</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com', "\n");
$writer = Writer::createFromString('john,doe,john.doe@example.com', "\r\n");
~~~

<p class="message-warning">Starting with <code>version 7.0</code> directly using the default constructor is no longer possible.</p>

## Switching from one class to the other

At any given time you can switch or create a new `League\Csv\Writer` or a new `League\Csv\Reader` from the current object. to do so you can use the following methods.

* the `newReader` to create a new `League\Csv\Reader` object;
* the `newWriter` to create a new `League\Csv\Writer` object;

Both methods accept an optional `$open_mode` parameter.

* When not explicitly set, the `$open_mode` default value is `r+` for both methods.
* If the initial object `$open_mode` parameter was not taken into account any new CSV object created with these methods won't take into account the given `$open_mode`.

~~~php
<?php

$reader = $writer->newReader('r+');
$newWriter = $reader->newWriter('a');
$anotherWriter = $newWriter->newWriter('r+');
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code> and <code>$anotherWriter</code> object are not the same as the <code>$writer</code> object!</p>