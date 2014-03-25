---
layout: layout
title: Loading
---

# Overview

The library is composed of two main classes:

* `League\Csv\Reader` to extract and filter data from a CSV
* `League\Csv\Writer` to insert new data into a CSV

Both classes share methods to instantiate, format and output the CSV.

## Class Instantiation

There's several ways to instantiate these classes:

~~~.language-php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = new Reader('/path/to/your/csv/file.csv');
$reader = new Reader(new SpliFileInfo('/path/to/your/csv/file.csv'), 'rt');
$reader = Reader::createFromString('john,doe,john.doe@example.com');

//or 

$writer = new Writer('/path/to/your/csv/file.csv', 'ab+');
$writer = new Writer(new SpliFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromString('john,doe,john.doe@example.com');
~~~

Both classes constructors take one optional parameter `$open_mode` representing
the file open mode used by the PHP fopen function.

The `$open_mode` parameter is taken into account if you instantiate your object with:

* a `SplFileInfo`
* a string path

The `$open_mode` parameter is ignore if you instantiate your object with:

* a `SplFileObject`
* a `SplTempFileObject`

When not explicitly set:

* The `League\Csv\Writer` `$open_mode` default value is `w`
* The `League\Csv\Reader` `$open_mode` default value is `r`

The static method `createFromString` is to be use if your data is a string. This
method takes no optional `$open_mode` parameter.

## CSV properties settings

Once your object is created you can optionally set:

* the CSV delimiter;
* the CSV enclosure;
* the CSV escape characters;
* the object `SplFileObject` flags;
* the CSV encoding charset if the CSV is not in `UTF-8`;

~~~.language-php
$reader = new Reader('/path/to/your/csv/file.csv');

$reader->setDelimeter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$reader->setEncoding('iso-8859-1');
~~~

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiter` method. **This method will only give you a hint**. 

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~.language-php
$reader = new Reader('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$reader->setEncoding('iso-8859-1');

$delimiter = $reader->detectDelimiter(10, [' ', '|']);
~~~

The more rows and delimiters you had, the more time and memory consuming the operation will be.

* If a single delimiter is found the method will return it;
* If multiple delimiters are found (ie: your CSV is not consistent) a `RuntimeException` is thrown;
* If no delimiter is found or your CSV is composed of a single column, `null` will be return;


## Switching from one class to the other

It is possible to switch between modes by using:

* the `League\Csv\Writer::getReader` method from the `League\Csv\Writer` class
* the `League\Csv\Reader::getWriter` method from the `League\Csv\Reader` class 

Both methods accept the optional $open_mode parameter.

~~~.language-php
$reader = $writer->getReader('r+');
$newWriter = $reader->getWriter('a'); 
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code>
object is not equal to the <code>$writer</code> object!</p>

## CSV stream filtering (since version 5.4)

Sometimes you may want to perform operations on the CSV as it is being read from or written to. This is useful, for instance, when dealing with a CSV encoded with a different charset than the one you are currently using. 

To deal with this issue and to broaden the use of this filtering mechanism, the library introduces the `League\Csv\Stream\FilterInterace` interface. Any object that implements this interface and that extends PHP native `php_user_filter` class will be able to filter on the fly the CSV data.

The interface contains the following methods:

* `FilterInterace::isRegistered` : **a static method** that tells if the filter is already registered;
* `FilterInterace::getName`: **a static method** that return the filter name;
* `FilterInterace::fetchFilterPath($path)`: **a public method** that returns the generated filter path from a given string path;

and redeclare the public methods from `php_user_filter`

* `FilterInterace::onCreate`: called when creating the filter;
* `FilterInterace::onClose`: called when closing the filter;
* `FilterInterace::filter`: called when applying the filter;

An implementation of this interface can be found by looking at the source code of the bundle filter class `League\Csv\Stream\EncodingFilter`. This class helps transcode on the fly any given CSV document from one charset to another. **Be careful, this class only works when reading from the CSV data not when writting to it.**

Once your class is ready you can specify it as an optional `$stream_filter` argument at the end of the following methods signatures:

* `Reader::__construct`
* `Writer::__construct`
* `Reader::getWriter`
* `Writer::getReader`

<p class="message-warning">The <code>$stream_filter</code> object will only be taken into account when the <code>$path</code> is a valid string.</p>

See below an example using `League\Csv\Stream\EncodingFilter` to illustrate:

~~~.language-php

use \League\Csv\Stream\EncodingFilter;

$encoder = new EncodingFilter;
$encoder->setEncodingFrom('iso-8859-15');
$encoder->setEncodingTo('UTF-8');
$reader = new Reader('/path/to/my/file.csv', 'r', $encoder);
foreach ($reader as $row) {
	//the content of row is automatically converted from iso-8859-15 to UTF-8 on the fly 
}
~~~

There's another implementation example that you can found in the [example folder](https://github.com/thephpleague/csv/blob/master/examples/stream.php "Uppercase Streaming Filter example"). This time around, the [Uppercase Stream Filter](https://github.com/thephpleague/csv/blob/master/examples/lib/UppercaseFilter.php) works on reading from and writing to the CSV.