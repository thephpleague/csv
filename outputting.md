---
layout: default
title: Outputting
permalink: outputting/
---

# Outputting & Downloads

With both `Reader` and `Writer` you can output the data that has been read or
generated in a uniform way.

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code>. It is recommended to do so with a <code>League\Csv\Reader</code> class to avoid loosing the file cursor position and get unexpected results when inserting data.</p>

## Iterate over the CSV

Using the foreach construct:

~~~php
foreach ($reader as $row) {
    //do something meaningfull here with $row !!
    //$row is an array where each item represent a CSV data cell
}
~~~

## Convert to XML

Use the toXML method to convert the CSV data into a `DomDocument` object. This
method accepts 3 optionals arguments `$root_name`, `$row_name` and `$cell_name`
to help you customize the XML tree.

By default:

~~~php
$root_name = 'csv'
$row_name = 'row'
$cell_name = 'cell'
~~~

~~~php
$dom = $reader->toXML('data', 'item', 'cell');
~~~

## Convert to HTML table

Use the `toHTML` method to format the CSV data into an HTML table. This method
accepts an optional argument `$classname` to help you customize the table
rendering, by defaut the classname given to the table is `table-csv-data`.

~~~php
echo $reader->toHTML('table table-bordered table-hover');
~~~

## Convert to JSON

Use the `json_encode` function directly on the instantiated object.

~~~php
echo json_encode($reader);
~~~

## Show the CSV content

Use the echo construct on the instantiated object or use the `__toString` method.

~~~php
echo $reader;
// or
echo $reader->__toString();
~~~

## Transcoding the CSV

The recommended way to transcode your CSV in a UTF-8 compatible charset is to use the <a href="/filtering/">library stream filtering mechanism</a>. When this is not possible you can fallback to using the `setEncondingFrom` and `getEncondingFrom` methods.

<p class="message-warning"><strong>BC Break:</strong> <code>setEnconding</code> and <code>getEnconding</code> methods have been renamed <code>setEncondingFrom</code> and <code>getEncondingFrom</code> to remove any ambiguity</p>

~~~php
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
$reader->setEncodingFrom('iso-8859-15');
echo $reader; //the CSV will be transcoded from iso-8859-15 to UTF-8;
~~~

When using the outputting methods and the `json_encode` function, the data is internally converted into UTF-8 if `setEncodingFrom` is set to anything other than `UTF-8`.

## Force a file download

### output($filename = null)

If you only wish to make your CSV downloadable just use the output method to
return to the output buffer the CSV content.

~~~php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
~~~

The output method can take an optional argument `$filename`. When present you
can even omit most of the headers.

~~~php
$reader->output("name-for-your-file.csv");
~~~

## BOM

<p class="message-notice">added in version 6.3</p>

To improve interoperability with programs interacting with CSV, you can now manage the presence of a <abbr title="Byte Order Mark">BOM</abbr> character in your CSV content.

The BOM character to be useful needs to be prepended to your CSV. The character signal the endianness of the CSV and its sequence depend on the encoding character of your CSV. To help you work with BOM, we are adding the following constants to the `Reader` and the `Writer` class:

* `BOM_UTF8` : BOM for UTF-8;
* `BOM_UTF16_BE` : BOM for UTF-16 with Big-Endian;
* `BOM_UTF16_LE` : BOM for UTF-16 with Little-Endian;
* `BOM_UTF32_BE` : BOM for UTF-32 with Big-Endian;
* `BOM_UTF32_LE` : BOM for UTF-32 with Little-Endian;

They each represent the BOM value for each encoding character.

### getBOMOnInput()

This method will detect if a BOM character is present in your CSV content. The method the detected BOM if one is found or `null`.

~~~php

$reader = new Reader::createFromPat('path/to/your/file.csv');
$res = $reader->getBOMOnInput(); //$res equals null if no BOM is found

$reader = new Reader::createFromPat('path/to/your/msexcel.csv');
$res = $reader->getBOMOnInput(); //$res equals "\xFF\xFE";
~~~

If you wish to remove the BOM character you can rely on the `Reader` [extracting methods](/reading).

### setBOMOnOutput(bool $use_bom);

This method will manage the addition of a BOM character in front of your outputted CSV when you are:
- downloading a file using the `output` method
- ouputting the CSV directly using the `__toString()` method

### getBOMOnOutput()

This method will tell you at any given time what BOM sequence will be prepended to the CSV content.

<p class="message-info">For Backward compatibility by default <code>getBOMOnOutput</code> returns <code>null</code>.</p>

~~~php

$reader = new Reader::createFromPat('path/to/your/file.csv');
$res = $reader->getBOMOnOutput(); //$res equals null;
$reader->setBOMOnOutput(Reader::BOM_UTF16LE);
$res = $reader->getBOMOnOutput(); //$res equals "\xFF\xFE";
$reader->output("name-for-your-file.csv"); the BOM sequence is prepended to the CSV

~~~