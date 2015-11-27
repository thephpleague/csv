---
layout: default
title: Setting and Accessing CSV settings
---

# CSV properties

Once your object is [instantiated](/instantiation/) you can optionally set several CSV properties. The following methods works on both the `Reader` and the `Writer` class.

## Accessing and Setting CSV properties

### The delimiter character

~~~php
$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
~~~
The default delimiter character is `,`.

### The enclosure character

~~~php
$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
~~~
The default enclosure character is `"`.

### The escape character

~~~php
$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
~~~
The default escape character is `\`.

## Detecting CSV delimiter

### fetchDelimitersOccurrence(array $delimiters, $nbRows = 1)

<p class="message-notice">This method is introduced in version <code>7.2.0</code></p>

The method takes two arguments:

* an array containing the delimiters to check;
* an integer which represents the number of rows to scan (default to `1`);

~~~php
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

<p class="message-warning"><strong>Warning:</strong> This method only test the delimiters you gave it.</p>

## Writing mode only properties

The following properties only affect the CSV when you are writing or saving data to it.

### The newline sequence

The newline sequence is appended to each CSV newly inserted line. To improve interoperability with programs interacting with CSV and because the php `fputcsv` implementation has a hardcoded `"\n"`, we need to be able to replace this last `LF` code with one supplied by the developer.

~~~php
$csv->setNewline("\r\n");
$newline = $csv->getNewline(); //returns "\r\n"
~~~
The default newline sequence is `\n`;

### The BOM character

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content.

Detect the current BOM character is done using the `getInputBOM` method. This method returns the currently used BOM character or `null` if none is found or recognized.

~~~php
$bom = $csv->getInputBOM();
~~~

You can of course set the outputting BOM you want your CSV to be associated with.

~~~php
$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~
The default output `BOM` character is set to `null`.

<p class="message-info">Please refer to <a href="/bom/">the BOM character dedicated documentation page</a> for more informations on how the library helps you manage this feature.</p>

## Conversion only properties

The following properties and method only works when converting CSV document into other available format.

### The encoding charset

To convert your CSV document into another format it must be encoded in UTF-8.

When this is not the case, you should transcode it using the <a href="/filtering/">library stream filtering mechanism</a>.

When this is not applicable you can fallback by providing the CSV original encoding charset to the CSV class using the following method:

~~~php
$reader->setEncodingFrom('iso-8859-15');
echo $reader->getEncodingFrom(); //returns iso-8859-15;
~~~

By default `getEncodingFrom` returns `UTF-8` if `setEncodingFrom` was not used.

<div class="message-warning">The encoding properties have no effect when reading or writing to a CSV document. You should instead use <a href="/filtering/">the Stream Filter API</a> or <a href="/inserting/#row-formatting">the Writing Formatter API</a>.</div>

~~~php
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
//we are using the setEncodingFrom method to transcode the CSV into UTF-8
$reader->setEncodingFrom('iso-8859-15');
echo json_encode($reader);
//the CSV is transcoded from iso-8859-15 to UTF-8
//before being converted to JSON format;
echo $reader; //outputting the data is not affected by the conversion
~~~