---
layout: default
title: Instantiation using named constructors
---

# Instantiation

The library is composed of two main classes:

* `League\Csv\Reader` to read data from a CSV
* `League\Csv\Writer` to write new data into a CSV

Both classes extend the `League\Csv\AbstractCsv` class and as such share methods to be instantiated.

## Instantiating a new CSV object

Because CSVs come in different forms there are several ways to instantiate the library CSV objects.
Below you will find **the recommended ways** to create a CSV object.

### createFromPath($path, $open_mode)

This named constructor will instante a CSV object *à la* `fopen`:

* The `$path` parameter can be:
    * a `SplFileInfo` object, the string path will be fetch from the object public methods.
    * an object implementing the `__toString` method the path will be the object string representation.
    * a string.

<p class="message-warning"><strong>Warning:</strong> The method throws an <code>InvalidArgumentException</code> if a <code>SplTempFileObject</code> is given as no path can be retrieve from such object.</p>
* This `$open_mode` parameter is **always** take into account and defaults to `r+` if none is supplied.

The resulting string and `$open_mode` parameters are used to lazy load internally a `SplFileObject` object.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
//the $reader object will use the 'r+' open mode as no `open_mode` parameter was supplied.
$writer = Writer::createFromPath(new SplFileObject('/path/to/your/csv/file.csv', 'a+'), 'w');
//the $writer object open mode will be 'w'!!
~~~

### createFromFileObject(SplFileObject $obj)

If you have a `SplFileObject` and you want to directly work with it you should use the named constructor `createFromFileObject`. This method accepts only one single parameter the `SplFileObject` object.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject());

~~~

### createFromString($str, $newline = "\n")

<p class="message-notice">The <code>$newline</code> argument was added in version 7.0</p>

If you have a raw CSV string you should use the named constructor `createFromString`. This method accepts two parameters:

- the raw CSV string.
- the newline character added at the end of the raw CSV string

If no newline character is specified, the newline character used will be `\n` to match the newline added by PHP `fputcsv` function.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com', "\n");
$writer = Writer::createFromString('john,doe,john.doe@example.com', "\r\n");

~~~

### Default Constructors

For backward compatibility you can still directly instantiate your CSV object with the constructor. The constructor takes 2 parameters:

* A `$path` which can be a `SplFileInfo`, an object that implements the `__toString` method or a string;
* A `$open_mode` which is ignore if you instantiate your object with a `SplFileObject`;

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = new Reader(new SplFileObject('/path/to/your/csv/file.csv'), 'wb');
// the $open_mode parameter is not taken into account
$writer = new Writer(new SplTempFileObject);
//in both case the object is directly used internally by the library

~~~

When using an object other than `SplFileObject` with the default class constructor, the library uses lazyloading.

## Switching from one class to the other

At any given time you can switch or create a new `League\Csv\Writer` or a new `League\Csv\Reader` from the current object. to do so you can use the following methods.

* the `newReader` to create a new `League\Csv\Reader` object;
* the `newWriter` to create a new `League\Csv\Writer` object;

Both methods accept an optional `$open_mode` parameter.

* When not explicitly set, the `$open_mode` default value is `r+` for both methods.
* If the initial object `$open_mode` parameter was not taken into account any new CSV object created with these methods won't take into account the given `$open_mode`.

~~~php
$reader = $writer->newReader('r+');
$newWriter = $reader->newWriter('a');
$anotherWriter = $newWriter->newWriter('r+');
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code> and <code>$anotherWriter</code> object are not the same as the <code>$writer</code> object!</p>