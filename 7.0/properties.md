---
layout: default
title: Setting and Accessing CSV settings
---

# CSV properties

Once your object is [instantiated](/7.0/instantiation/) you can optionally set several CSV properties. The following methods works on both the `Reader` and the `Writer` class.

## Accessing and Setting CSV properties

### The delimiter character

~~~php
<?php

$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
~~~
The default delimiter character is `,`.

### The enclosure character

~~~php
<?php

$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
~~~
The default enclosure character is `"`.

### The escape character

<p class="message-warning"><strong>Warning:</strong> The library depends on PHP <code>SplFileObject</code> class. Since this class exhibits <a href="https://bugs.php.net/bug.php?id=55413" target="_blank">a reported bug</a>, <strong>Data using the escape character a correctly escaped but the escape character is not removed from the CSV content</strong>.<br>
A possible workaround to this issue while waiting for a PHP bug fix is to <a href="/7.0/reading/#using-a-callable-to-modify-the-returned-resultset">register a callable that will format your content.</a></p>

~~~php
<?php

$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
~~~
The default escape character is `\`.

### The SplFileObject flags

`League\Csv` objects rely internally on the `SplFileObject` class. In order to fine tune the class behavior you can adjust the [SplFileObject flags](http://php.net/manual/en/class.splfileobject.php#splfileobject.constants) used.

~~~php
<?php

$csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$flags = $csv->getFlags(); //returns an integer
~~~

<p class="message-notice">Since version <code>7.0.1</code>, the <code>setFlags</code> method has been fixed to prevent a <a href="https://bugs.php.net/bug.php?id=69181" target="_blank">bug in SplFileObject</a>.</p>

<p class="message-notice">Since version <code>7.2.0</code>, the flags on instantiaton are have been changed to correct a bug when parsing row cells with multiple lines</p>

- On instantiation the flags set are :
    - `SplFileObject::READ_CSV`
    - `SplFileObject::READ_AHEAD`
    - `SplFileObject::SKIP_EMPTY`

- On update you can add or remove any `SplFileObject` flags except for the `SplFileObject::READ_CSV` flag.

## Detecting CSV delimiter

### fetchDelimitersOccurrence(array $delimiters, $nbRows = 1)

<p class="message-notice">This method is introduced in version <code>7.2.0</code></p>

The method takes two arguments:

* an array containing the delimiters to check;
* an integer which represents the number of rows to scan (default to `1`);

~~~php
<?php

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');

$delimiters_list = $reader->fetchDelimitersOccurrence([' ', '|'], 10);
// $delimiters_list can be the following
// [
//     '|' => 20,
//     ' ' => 0,
// ]
// This seems to be a consistent CSV with:
// - the delimiter "|" appearing 20 times in the 10 first rows
// - the delimiter " " never appearing
~~~

<p class="message-notice">This method only test the delimiters you gave it.</p>

### detectDelimiterList($nbRows = 1, array $delimiters = [])

<p class="message-warning">This method is deprecated since version <code>7.2.0</code> and will be remove in the next major release</p>

<p class="message-warning">If multiple delimiters share the same occurrences count only the last found delimiter will be returned in the response array.</p>

<p class="message-notice">This method will only give you a hint, a better approach is to ask the CSV provider for the document controls properties.</p>

If you are no sure about the delimiter you can ask the library to detect it for you using the `detectDelimiterList` method.

The method takes two arguments:

* the number of rows to scan (default to `1`);
* the possible delimiters to check (you don't need to specify the following delimiters as they are already checked by the method: `",", ";", "\t"`);

~~~php
<?php

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
// $delimiters_list can be the following
// [
//     20 => '|',
//     3 => ';'
// ]
// This is a inconsistent CSV with:
// - the delimiter "|" appearing 20 times in the 10 first rows
// - the delimiter ";" appearing 3 times in the 10 first rows
~~~

The more rows and delimiters you add, the more time and memory consuming the operation will be. The method returns an `array` of the delimiters found.

* If a single delimiter is found the array will contain only one delimiter;
* If multiple delimiters are found the array will contain the found delimiters sorted descendingly according to their occurences in the defined rows set;
* If no delimiter is found or your CSV is composed of a single column, the array will be empty;

<p class="message-warning"><strong>BC Break:</strong> Starting with version <code>7.0</code>, the index of each found delimiter represents the occurence of the found delimiter in the selected rows.</p>

Whenever a user creates a new CSV object using the `newWriter` or the `newReader` methods, the current CSV object properties are copied to the new instance.

## Writing mode only properties

The following properties only affect the CSV when you are writing or saving data to it.

### The newline sequence

The newline sequence is appended to each CSV newly inserted line. To improve interoperability with programs interacting with CSV and because the php `fputcsv` implementation has a hardcoded `"\n"`, we need to be able to replace this last `LF` code with one supplied by the developer.

~~~php
<?php

$csv->setNewline("\r\n");
$newline = $csv->getNewline(); //returns "\r\n"
~~~
The default newline sequence is `\n`;

<p class="message-notice">Since version 7.0, the <code>$newline</code> getter and setter methods are also available on the <code>Reader</code> class.</p>

### The BOM character

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content.

Detect the current BOM character is done using the `getInputBOM` method. This method returns the currently used BOM character or `null` if none is found or recognized.

~~~php
<?php

$bom = $csv->getInputBOM();
~~~

You can of course set the outputting BOM you want your CSV to be associated with.

~~~php
<?php

$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~
The default output `BOM` character is set to `null`.

<p class="message-info">Please refer to <a href="/7.0/bom/">the BOM character dedicated documentation page</a> for more informations on how the library helps you manage this feature.</p>

## Conversion only properties

The following properties and method only works when converting CSV document into other available format.

### The encoding charset

To convert your CSV document into another format it must be encoded in UTF-8.

When this is not the case, you should transcode it using the <a href="/7.0/filtering/">library stream filtering mechanism</a>.

When this is not applicable you can fallback by providing the CSV original encoding charset to the CSV class using the following method:

~~~php
<?php

$reader->setEncodingFrom('iso-8859-15');
echo $reader->getEncodingFrom(); //returns iso-8859-15;
~~~

By default `getEncodingFrom` returns `UTF-8` if `setEncodingFrom` was not used.

<div class="message-warning">The encoding properties have no effect when reading or writing to a CSV document. You should instead use <a href="/7.0/filtering/">the Stream Filter API</a> or <a href="/7.0/inserting/#row-formatting">the Writing Formatter API</a>.</div>

~~~php
<?php

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
//we are using the setEncodingFrom method to transcode the CSV into UTF-8
$reader->setEncodingFrom('iso-8859-15');
echo json_encode($reader);
//the CSV is transcoded from iso-8859-15 to UTF-8
//before being converted to JSON format;
echo $reader; //outputting the data is not affected by the conversion
~~~