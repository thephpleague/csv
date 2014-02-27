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

## Show the CSV content

Use the echo construct on the instantiated object or use the `__toString` method.

~~~.language-php
echo $writer;
// or
echo $writer->__toString();
~~~

## Convert to XML

Use the toXML method to convert the CSV data into a PHP DomDocument object. This
method accepts 3 optionals arguments `$root_name`, `$row_name` and `$cell_name` 
to help you customize the XML tree.

By default:

~~~.language-php
$root_name = 'csv'
$row_name = 'row'
$cell_name = 'cell'
$dom = $writer->toXML('data', 'item', 'cell');
~~~

## Convert to HTML table

Use the toHTML method to format the CSV data into an HTML table. This method 
accepts an optional argument $classname to help you customize the table 
rendering, by defaut the classname given to the table is table-csv-data.

~~~.language-php
echo $writer->toHTML('table table-bordered table-hover');
~~~

## Convert to JSON

Use the json_encode function directly on the instantiated object.

~~~.language-php
echo json_encode($writer);
~~~

When using the `toHTML()`, `toXML()` methods and the `json_encode` function,
the data is internally converted if needed into UTF-8.

## Force a file download

If you only wish to make your CSV downloadable just use the output method to 
return to the output buffer the CSV content.

~~~.language-php
$reader->setEncoding('ISO-8859-15');
header('Content-Type: text/csv; charset="'.$reader->getEncoding().'"');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
~~~

The output method can take an optional argument `$filename`. When present you
can even omit most of the headers.

~~~.language-php
$reader->setEncoding('ISO-8859-15');
$reader->output("name-for-your-file.csv");
~~~