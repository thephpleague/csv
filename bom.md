---
layout: default
title: CSV and BOM character
permalink: bom/
---

# Managing the BOM character

<p class="message-notice">added in version 6.3</p>

## Detecting the CSV BOM character

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content.

The BOM character to be useful needs to be the first character of your CSV. <a href="http://en.wikipedia.org/wiki/Endianness" target="_blank">The character signal the endianness</a> of the CSV and its value depends on the encoding character of your CSV. To help you work with `BOM`, we are adding the following constants to the `Reader` and the `Writer` class:

* `BOM_UTF8` : `UTF-8` `BOM`;
* `BOM_UTF16_BE` : `UTF-16` `BOM` with Big-Endian;
* `BOM_UTF16_LE` : `UTF-16` `BOM` with Little-Endian;
* `BOM_UTF32_BE` : `UTF-32` `BOM` with Big-Endian;
* `BOM_UTF32_LE` : `UTF-32` `BOM` with Little-Endian;

They each represent the `BOM` character for each encoding character.

### getBOMOnInput()

This method will detect if a `BOM` character is present in your CSV content. The method returns the detected `BOM` or `null`.

~~~php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$res = $reader->getBOMOnInput(); //$res equals null if no BOM is found

$reader = new Reader::createFromPat('path/to/your/msexcel.csv');
if (Reader::BOM_UTF16_LE == $reader->getBOMOnInput()) {
	//the CSV file is encoded using UTF-16 LE
}
~~~

If you wish to remove the BOM character while processing your data, you can rely on the `Reader` [extracting methods](/reading) to do so.

## Adding the BOM character to your CSV


### setBOMOnOutput(bool $use_bom);

This method will manage the addition of a BOM character in front of your outputted CSV when you are:

- downloading a file using the `output` method
- ouputting the CSV directly using the `__toString()` method

### getBOMOnOutput()

This method will tell you at any given time what BOM sequence will be prepended to the CSV content.

<p class="message-info">For Backward compatibility by default <code>getBOMOnOutput</code> returns <code>null</code>.</p>

~~~php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$res = $reader->getBOMOnOutput(); //$res equals null;
$reader->setBOMOnOutput(Reader::BOM_UTF16LE);
$res = $reader->getBOMOnOutput(); //$res equals "\xFF\xFE";
$reader->output("name-for-your-file.csv"); the BOM sequence is prepended to the CSV

~~~

## Software dependency

Depending on your operating system and on the software you are using to read you CSV you may need to adapt the encoding character and add its corresponding BOM character to enable the use your CSV in the software. Do keep in mind that out of the box `League\Csv` assume that your are using a `UTF-8` CSV.

### MS Excel

On Windows, MS Excel, expect an UTF-8 encoded CSV with its corresponding `BOM` character. So to fullfill this requirement, you simply need to add the `UTF-8` `BOM` character as explained below:

~~~php
use League\Csv\Reader;

require '../vendor/autoload.php';

$csv = Reader::createFromPath('/path/to/my/file.csv');
$csv->setBOMOnOutput(Reader::BOM_UTF8);
$csv->output('test.csv');

~~~

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` with `tab` delimiters. Since `League\Csv` default output is `UTF-8` we will need to update the CSV encoding character and its tab property.how we download the CSV.

~~~php
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';

stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");

$csv = Writer::createFromPath('/path/to/my/file.csv');
$csv->appendStreamFilter(FilterTranscode::FILTER_NAME."UTF-8:UTF-16LE");
$csv->setDelimiter("\t");
$csv->insertAll(...); //you can insert new data with tab delimiter
$csv->setBOMOnOutput(Writer::BOM_UTF16_LE);
$csv->output('test.csv');

~~~

of note, we :

- used the [filtering capability](/filtering) of the library to first convert the CSV from `UTF-8` to `UTF-16`
- set the delimiter to the `tab` character otherwise the cells won't be detected
- add the `UTF-16 LE` `BOM` character

The CSV is now readable out of the box by MS Excel on Mac OS.

