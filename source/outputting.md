---
layout: layout
title: Outputting
---

# Outputting & Downloads

With both `Reader` and `Writer` you can output the data that has been read or 
generated in a uniform way.

## Iterate over the CSV

Using the foreach construct:

~~~.language-php
foreach ($reader as $row) {
    //do something meaningfull here with $row !!
    //$row is an array where each item represent a CSV data cell
}
~~~

## Transcoding the CSV

The recommended way to transcode your CSV in a UTF-8 compatible charset is to use the <a href="/filtering/">library stream filtering mechanism</a>. When this is not possible you can fallback using the `setEncondignFrom` and `getEncondignFrom` methods.

<p class="message-warning"><strong>BC Break:</strong> <code>setEnconding</code> and <code>getEnconding</code> methods have been removed since version 6.0 and are replaced by <code>setEncondingFrom</code> and <code>getEncondingFrom</code> for naming consistency</p>

~~~.language-php
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
$reader->setEncodingFrom('iso-8859-15');
echo $reader; //the CSV will be transcoded from iso-8859-15 to UTF-8;
~~~

When using the outputting methods and the `json_encode` function, the data is internally converted into UTF-8 if `setEncodingFrom` is set to anything other than `UTF-8`.

## Show the CSV content

Use the echo construct on the instantiated object or use the `__toString` method.

~~~.language-php
echo $writer;
// or
echo $writer->__toString();
~~~

## Convert to XML

Use the toXML method to convert the CSV data into a `DomDocument` object. This
method accepts 3 optionals arguments `$root_name`, `$row_name` and `$cell_name` 
to help you customize the XML tree.

By default:

~~~.language-php
$root_name = 'csv'
$row_name = 'row'
$cell_name = 'cell'
~~~

~~~.language-php
$dom = $writer->toXML('data', 'item', 'cell');
~~~

## Convert to HTML table

Use the `toHTML` method to format the CSV data into an HTML table. This method 
accepts an optional argument `$classname` to help you customize the table 
rendering, by defaut the classname given to the table is `table-csv-data`.

~~~.language-php
echo $writer->toHTML('table table-bordered table-hover');
~~~

## Convert to JSON

Use the `json_encode` function directly on the instantiated object.

~~~.language-php
echo json_encode($writer);
~~~

## Force a file download

If you only wish to make your CSV downloadable just use the output method to 
return to the output buffer the CSV content.

~~~.language-php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
~~~

The output method can take an optional argument `$filename`. When present you
can even omit most of the headers.

~~~.language-php
$reader->output("name-for-your-file.csv");
~~~