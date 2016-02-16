---
layout: default
title: CSV and BOM character
---

# Managing the BOM character

## Detecting the CSV BOM character

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content. <a href="http://en.wikipedia.org/wiki/Endianness" target="_blank">The character signals the endianness</a> of the CSV and its value depends on the CSV encoding character. To help you work with `BOM`, we are adding the following constants to the `Reader` and the `Writer` class:

* `BOM_UTF8` : `UTF-8` `BOM`;
* `BOM_UTF16_BE` : `UTF-16` `BOM` with Big-Endian;
* `BOM_UTF16_LE` : `UTF-16` `BOM` with Little-Endian;
* `BOM_UTF32_BE` : `UTF-32` `BOM` with Big-Endian;
* `BOM_UTF32_LE` : `UTF-32` `BOM` with Little-Endian;

They each represent the `BOM` character for each encoding character.

### getInputBOM()

This method will detect and return the `BOM` character used in your CSV if any.

~~~php
<?php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$res = $reader->getInputBOM(); //$res equals null if no BOM is found

$reader = new Reader::createFromPat('path/to/your/msexcel.csv');
if (Reader::BOM_UTF16_LE == $reader->getInputBOM()) {
	//the CSV file is encoded using UTF-16 LE
}
~~~

If you wish to remove the BOM character while processing your data, you can rely on the [query filters](/7.0/query-filtering/#stripbomstatus) to do so.

## Adding the BOM character to your CSV

### setOutputBOM($bom = null);

This method will manage the addition of a BOM character in front of your outputted CSV when you are:

- downloading a file using the `output` method
- ouputting the CSV directly using the `__toString()` method

`$bom` is a string representing the BOM character. To remove the `BOM` character just set `$bom` to an empty value like `null` or an empty string.

<p class="message-info">To ease writing the sequence you should use the <code>BOM_*</code> constants.</p>

### getOutputBOM()

This method will tell you at any given time what `BOM` character will be prepended to the CSV content.

<p class="message-info">For Backward compatibility by default <code>getOutputBOM</code> returns <code>null</code>.</p>

~~~php
<?php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$reader->getOutputBOM(); //$res equals null;
$reader->setOutputBOM(Reader::BOM_UTF16LE);
$res = $reader->getOutputBOM(); //$res equals "\xFF\xFE";
echo $reader; //the BOM sequence is prepended to the CSV

~~~

## Software dependency

Depending on your operating system and on the software you are using to read/import your CSV you may need to adjust the encoding character and add its corresponding BOM character to your CSV.

<p class="message-warning">Out of the box, <code>League\Csv</code> assumes that your are using a <code>UTF-8</code> encoded CSV without any <code>BOM</code> character.</p>

In the examples below we will be using an existing CSV as a starting point. The code may vary if you are creating the CSV from scratch.

### MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setOutputBOM(Reader::BOM_UTF8);
//BOM detected and adjusted for the output
echo $reader->__toString();

~~~

### MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` using the `tab` character as delimiter. Here's an example on how to meet those requirements using the `League\Csv` package.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';

//the current CSV is UTF-8 encoded with a ";" delimiter
$origin = Reader::createFromPath(__DIR__.'/data/prenoms.csv');

//let's convert the CSV to use a tab delimiter.

//we must use a real temp file to be able to rewind the cursor file
//without loosing the modifications
$writer = Writer::createFromPath('/tmp/toto.csv', 'w');

//we set the tab as the delimiter character
$writer->setDelimiter("\t");

//we insert csv data
$writer->insertAll($origin);

//let's switch to the Reader object
//Writer::output will failed because of the open mode
$csv = $writer->newReader();

//we register a Stream Filter class to convert the CSV into the UTF-16 LE
stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");
$csv->appendStreamFilter(FilterTranscode::FILTER_NAME."UTF-8:UTF-16LE");

//we detect and adjust the output BOM to be used
$csv->setOutputBOM(Reader::BOM_UTF16_LE);
//all is good let's output the results
$csv->output('mycsvfile.csv');

~~~

Of note, we used the [filtering capability](/7.0/filtering) of the library to convert the CSV encoding character from `UTF-8` to `UTF-16 LE`.

You can found the code and the associated filter class in the [examples directory](https://github.com/thephpleague/csv/tree/master/examples).