---
layout: layout
title: Loading
---

# Overview

The library is composed of two main classes:

* `League\Csv\Reader` to extract and filter data from a CSV
* `League\Csv\Writer` to insert new data into a CSV

Both classes extends the `League\Csv\AbstractCsv` class and as such share methods to instantiate, format and output the CSV.

<h2 id="instantiation">Class Instantiation</h2>

There's several ways to instantiate these classes:

~~~.language-php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv', 'rt');
$reader = Reader::createFromString('john,doe,john.doe@example.com');
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$reader = new Reader('/path/to/your/csv/file.csv');
$reader = new Reader(new SpliFileInfo('/path/to/your/csv/file.csv'), 'rt');

//or 

$writer = Writer::createFromPath(new SpliFileObject('/path/to/your/csv/file.csv'), 'ab+');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromFileObject(new SplTempFileObject);
$writer = new Writer('/path/to/your/csv/file.csv', 'ab+');
$writer = new Writer(new SpliFileObject('/path/to/your/csv/file.csv'));
~~~

**The recommended way** to create a CSV object from:

* a raw string is to use the named constructor `createFromString($str)`;
* a `SplFileObject` is to use the named constructor `createFromFileObject(new SplTemFileObject)`;
* a file path  *à la* `fopen`  is to use the named constructor `createFromPath($path, $open_mode)`. The `$path` parameter can be a `SplFileInfo`, an object that implements the `__toString` method or a string. This `$open_mode` parameter is **always** take into account and defaults to `r+` if none is supplied. 

<p class="message-info">The <code>createFromFileObject</code>  and <code>createFromPath</code> named constructors were added in <strong>version 6.0</strong>.</p> 

For backward compatibility you can still directly instantiate your CSV object with the constructor. The constructor takes 2 parameters:

* A `$path` which can be a `SplFileInfo`, an object that implements the `__toString` method or a string;
* A `$open_mode` which is ignore if you instantiate your object with a `SplFileObject`;

## CSV properties settings

Once your object is created you can optionally set:

* the CSV delimiter;
* the CSV enclosure;
* the CSV escape characters;
* the object `SplFileObject` flags;

~~~.language-php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setDelimiter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
~~~

### detectDelimiterList($nbRows = 1, array $delimiters = []) *new in version 6*

<p class="message-warning"><strong>BC Break:</strong> the <code>detectDelimiterList</code> method replaces the removed method <code>detectDelimiter</code> !</p>

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiterList` method. **This method will only give you a hint**. 

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~.language-php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
~~~

The more rows and delimiters you add, the more time and memory consuming the operation will be. The method returns an `array` of the delimiters found.

* If a single delimiter is found the array will contain only one delimiter;
* If multiple delimiters are found the array will contain the found delimiters sorted descendingly according to their occurences in the defined rows set;
* If no delimiter is found or your CSV is composed of a single column, the array will be empty;

## Switching from one class to the other

At any given time you can switch or create a new `League\Csv\Writer` or a new `League\Csv\Reader` from the current object. to do so you can use the following methods.

* the `newReader` to create a new `League\Csv\Reader` object;
* the `newWriter` to create a new `League\Csv\Writer` object;

Both methods accept the optional $open_mode parameter. When not explicitly set, the `$open_mode` default value is `r+` for both methods.

~~~.language-php
$reader = $writer->newReader('r+');
$newWriter = $reader->newWriter('a'); 
$anotherWriter = $newWriter->newWriter('r+'); 
~~~

<p class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code> and <code>$anotherWriter</code> object are not the same as the <code>$writer</code> object!</p>
