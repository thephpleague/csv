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

### getInputBOM()

This method will detect if a `BOM` character is present in your CSV content. The method returns the detected `BOM` or `null`.

~~~php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$res = $reader->getInputBOM(); //$res equals null if no BOM is found

$reader = new Reader::createFromPat('path/to/your/msexcel.csv');
if (Reader::BOM_UTF16_LE == $reader->getInputBOM()) {
	//the CSV file is encoded using UTF-16 LE
}
~~~

If you wish to remove the BOM character while processing your data, you can rely on the `Reader` [extracting methods](/reading) to do so.

## Adding the BOM character to your CSV


### setOutputBOM($bom = null);

This method will manage the addition of a BOM character in front of your outputted CSV when you are:

- downloading a file using the `output` method
- ouputting the CSV directly using the `__toString()` method

`$bom` is a string representing the BOM character or `null` to reset the setting.

<p class="message-info">To ease writing the sequence you should use the <code>BOM_*</code> constant.</p>

### getOutputBOM()

This method will tell you at any given time what BOM character will be prepended to the CSV content.

<p class="message-info">For Backward compatibility by default <code>getOutputBOM</code> returns <code>null</code>.</p>

~~~php

$reader = new Reader::createFromPath('path/to/your/file.csv');
$reader->getOutputBOM(); //$res equals null;
$reader->setOutputBOM(Reader::BOM_UTF16LE);
$res = $reader->getOutputBOM(); //$res equals "\xFF\xFE";
echo $reader; the BOM sequence is prepended to the CSV

~~~

## Software dependency

Depending on your operating system and on the software you are using to read you CSV you may need to adapt the encoding character and add its corresponding BOM character to enable the use your CSV in the software. 

<p class="message-warning">Do keep in mind that out of the box <code>League\Csv</code> assume that your are using a `UTF-8` CSV.</p>

### MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. So to fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
use League\Csv\Reader;

require '../vendor/autoload.php';

$csv = Reader::createFromPath('/path/to/my/file.csv');
//detect and adjust the output BOM to be used
if (Reader::BOM_UTF8 != $reader->getInputBOM()) {
    $reader->setOutputBOM(Reader::BOM_UTF8);
}
$csv->output('test.csv');

~~~

### MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` with `tab` delimiters. Since `League\Csv` default output is `UTF-8` we will need to update the CSV encoding character and its tab property.how we download the CSV.

~~~php
use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';

//the current CSV is UTF-8 encoded with a ";" delimiter
$csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');

//To be open in MacOS Excel a CSV must
// - be encoded in UTF16-LE
// - use tab delimiter

//let's convert the CSV to be UTF-16_LE encoded with a tab delimiter.

//we must use `createFromPath` to be able to use the stream capability
//we must use a temp file to be able to rewind the cursor file without loosing
//the modification
$writer = Writer::createFromPath('/tmp/toto.csv', 'w');
$writer->setNullHandlingMode(Writer::NULL_AS_EMPTY);

// we register a Transcode Filter class to convert the CSV into the proper encoding charset
stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");
$writer->appendStreamFilter(FilterTranscode::FILTER_NAME."UTF-8:UTF-16LE");

//we set the tab as the delimiter character
$writer->setDelimiter("\t");

//we insert csv data
$writer->insertAll($csv);

//let's switch to the Reader object
//Writer::output will failed because of the open mode
$reader = $writer->newReader();
//detect and adjust the output BOM to be used
if (Reader::BOM_UTF16_LE != $reader->getInputBOM()) {
    $reader->setOutputBOM(Reader::BOM_UTF16_LE);
}
//let's add the corresponding BOM
$reader->output('mycsvfile.csv');

~~~

of note, we :

- used the [filtering capability](/filtering) of the library to first convert the CSV from `UTF-8` to `UTF-16 LE`
- set the delimiter to the `tab` character otherwise the cells won't be detected
- add the `BOM UTF-16 LE` character if missing

The CSV is now readable out of the box by MS Excel on Mac OS.

You can found this code and the associated filter class in the [examples directory](https://github.com/thephpleague/csv/tree/master/examples).