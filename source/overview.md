---
layout: layout
title: Loading
---

# Overview


<div class="message-warning"><strong>Warning:</strong>If you are on a Mac OS X Server, add the following lines before using the library to help <a href="http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues">PHP detect line ending in Mac OS X</a>.
<pre><code class="language-php">if (! ini_get(&quot;auto_detect_line_endings&quot;)) {
    ini_set(&quot;auto_detect_line_endings&quot;, true);
}
</code></pre>
</div>

<p class="message-info">If you have your LC_CTYPE set to a locale that's using UTF-8 and you try to parse a file that's not in UTF-8, PHP will cut your fields the moment it encounters a byte it can't understand (i.e. any outside of ASCII that doesn't happen to be part of a UTF-8 character which it likely isn't). <a href="https://gist.github.com/pilif/9137146">This gist will show you a possible solution</a> to this problem by using <a href="http://www.php.net/manual/en/stream.filters.php">PHP stream filter</a>. This tip is from <a href="https://github.com/pilif">Philip Hofstetter</a></p>

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
* the `League\Csv\Reader::getWriter` method from the `League\Csv\Reader` class this method accept the optional $open_mode parameter.

~~~.language-php
$reader = $writer->getReader();
$newWriter = $reader->getWriter('a'); 
~~~

<div class="message-warning"><strong>Warning:</strong> be careful the <code>$newWriter</code>
object is not equal to the <code>$writer</code> object!</div>
