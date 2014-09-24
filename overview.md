---
layout: default
title: Loading
permalink: overview/
---

# Overview

The library is composed of two main classes:

* `League\Csv\Reader` to query and extract data from a CSV
* `League\Csv\Writer` to insert new data into a CSV

Both classes extend the `League\Csv\AbstractCsv` class and as such share methods to instantiate, format and output the CSV.

<h2 id="instantiation">CSV Objects instantiation</h2>

Because CSVs come in different forms there are several ways to instantiate the library CSV objects.
Below you will find **the recommended ways** to create a CSV object.

### createFromString($str)

If you have a raw CSV string you should use the named constructor `createFromString`. This method accepts only one single parameter the raw CSV string.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromString('john,doe,john.doe@example.com');

~~~

### createFromFileObject(SplFileObject $obj) *- since version 6.0*

If you have a `SplFileObject` and you want to directly work with it you should use the named constructor `createFromFileObject`. This method accepts only one single parameter the `SplFileObject` object.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject);

~~~

### createFromPath($path, $open_mode) *- since version 6.0*

For any other purpose you should rely on the named constructor `createFromPath`. You will be instantiating a CSV object *à la* `fopen`:

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
//the $reader object open mode will be 'r+' as no open_mode parameter was given !!
$writer = Writer::createFromPath(new SplFileObject('/path/to/your/csv/file.csv', 'a+'), 'w');
//the $writer object open mode will be 'w'!!
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

## CSV properties settings

Once your object is created you can optionally set:

* the CSV delimiter;
* the CSV enclosure;
* the CSV escape characters;
* the object `SplFileObject` flags;

~~~php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setDelimiter(',');
$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
~~~

### detectDelimiterList($nbRows = 1, array $delimiters = []) *- since version 6.0*

<p class="message-warning"><strong>BC Break:</strong> the <code>detectDelimiterList</code> method replaces the removed method <code>detectDelimiter</code> !</p>

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiterList` method. **This method will only give you a hint**. 

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~php
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

<p class="message-warning"><strong>BC Break:</strong> the <code>League\Csv\Reader::getWriter</code> and <code>League\Csv\Writer::getReader</code>methods are removed in favor of the new methods that are available on both classes.</p>

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
