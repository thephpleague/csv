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

* The `$open_mode` default value is `r+` for both classes.

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

<p class="message-info">Since version 5.4: it is recommended not to directly set the encoding of the document but to rely on a <a href="/filtering/">Stream Filter Plugin</a> to convert you CSV into an UTF-8 encoded document.</p>


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

Both methods accept the optional $open_mode parameter. When not explicitly set, the `$open_mode` default value is `r+` for both methods.

~~~.language-php
$reader = $writer->getReader('r+');
$newWriter = $reader->getWriter('a'); 
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code>
object is not equal to the <code>$writer</code> object!</p>