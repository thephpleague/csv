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

$reader = Reader::createFromPath('/path/to/your/csv/file.csv', 'rt');
$reader = Reader::createFromString('john,doe,john.doe@example.com');
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));

//or 

$writer = Writer::createFromPath(new SpliFileObject('/path/to/your/csv/file.csv'), 'ab+');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromFileObject(new SplTempFileObject);
~~~

Both classes use named constructors to ease object instantiation. If you want to create a CSV object from:

* a raw string use the `createFromString` static method;
* a `SplFileObject` use the `createFromFileObject` static method;
* a file path  *à la* `fopen` use the static method `createFromPath`. This method takes an optional `$open_mode` parameter which default value is `r+`.

For simplicity it is recommended to only use the named constructors. For backward compatibility you can still directly instantiate your CSV object with the constructor, but the optional `$open_mode` parameter is

taken into account if you instantiate your object with:

* a `SplFileInfo`
* a string path

ignore if you instantiate your object with:

* a `SplFileObject`
* a `SplTempFileObject`

## CSV properties settings

Once your object is created you can optionally set:

* the CSV delimiter;
* the CSV enclosure;
* the CSV escape characters;
* the object `SplFileObject` flags;
* the CSV encoding source

~~~.language-php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setDelimiter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$reader->setEncodingFrom('iso-8859-15');
~~~

The recommended way to transcode you CSV in a UTF-8 compatible charset is to use the <a href="/filtering/">library stream filtering mechanism</a>. When this is not possible you may fallback by using the `setEncondignFrom` and `getEncondignFrom` methods.

<p class="message-warning"><strong>Warning:</strong> <code>set/getEncoding</code> methods have been deprecated and are schedule to be remove on the next major release. For backward compatibility, <code>setEncoding</code> is an alias of <code>setEncondignFrom</code> and <code>getEncoding</code> is an alias of <code>getEncondignFrom</code></p>

### detectDelimiter($nbRows = 1, array $delimiters = []) *since version 5.1*

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiter` method. **This method will only give you a hint**. 

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~.language-php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

$delimiter = $reader->detectDelimiter(10, [' ', '|']);
~~~

The more rows and delimiters you add, the more time and memory consuming the operation will be.

* If a single delimiter is found the method will return it;
* If multiple delimiters are found (ie: your CSV is not consistent) a `RuntimeException` is thrown;
* If no delimiter is found or your CSV is composed of a single column, `null` will be return;

## Switching from one class to the other

<p class="message-warning">The <code>getReader</code> and <code>getWriter</code> methods have been deprecated and will be remove in the next major version release. For backward compatibility, the methods are now aliases of the <code>newReader</code> and <code>newWriter</code> methods.</p>

At any given time you can switch or create a new `League\Csv\Writer` or a new `League\Csv\Reader` from the current object. to do so you can use the following methods.

* the `League\Csv\Writer::newReader` method from the `League\Csv\Writer` class
* the `League\Csv\Reader::newWriter` method from the `League\Csv\Reader` class 

Both methods accept the optional $open_mode parameter. When not explicitly set, the `$open_mode` default value is `r+` for both methods.

~~~.language-php
$reader = $writer->newReader('r+');
$newWriter = $reader->newWriter('a'); 
$anotherWriter = $newWriter->newWriter('r+'); 
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code> and <code>$anotherWriter</code> object are not equal to the <code>$writer</code> object!</p>
