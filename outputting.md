---
layout: default
title: Basic Usage
---

# Basic usage

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid loosing the file cursor position and getting unexpected results when inserting new data.</p>

Once your CSV object is instantiated and configured, there are a number of methods available to interact with it. For starter you can iterate over your newly object to extract each CSV row using the `foreach` construct.

~~~php
$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
foreach ($reader as $index => $row) {
    //do something meaningfull here with $row !!
    //$row is an array where each item represent a CSV data cell
    //$index is the CSV row index
}
~~~

<p class="message-info"><strong>Tips:</strong> You can do more complex iterations using the extract methods as described in the <a href="/reading/">reading documentation page</a></p>

## Outputting the CSV

### __toString()

Use the `echo` construct on the instantiated object or use the `__toString` method to show the CSV full content.

~~~php
echo $reader;
// or
echo $reader->__toString();
~~~

### output($filename = null)

If you only wish to make your CSV downloadable by forcing a file download just use the `output` method to force the use of the output buffer on the CSV content.

~~~php
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');
$reader->output();
~~~

The output method can take an optional argument `$filename`. When present you
can even remove more headers.

~~~php
$reader->output("name-for-your-file.csv");
~~~

<p class="message-info">For interoperabilitry, you may wish to add a BOM character to the output. For more informations please refer to the <a href="/bom/">BOM section</a>.</p>

<p class="message-warning">The output methods are only affected by the file encoding character if you transcode your CSV in a UTF-8 compatible charset using the <a href="/filtering/">library stream filtering mechanism</a>.</p>

## Convert the CSV

<p class="message-warning"><strong>BC Break: </strong> Starting with version 7.0 conversion method behavior is affected by <code>Csv\Reader</code> filtering methods. Please refer to the <a href="/reading/">extracting data section</a> for more information</p>

### Convert into JSON format

Use the `json_encode` function directly on the instantiated object.

~~~php
echo json_encode($reader);
~~~

### Convert to XML

Use the `toXML` method to convert the CSV data into a `DomDocument` object. This
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

### Convert to HTML table


Use the `toHTML` method to convert the CSV data into an HTML table. This method
accepts an optional argument `$classname` to help you customize the table
rendering, by defaut the classname given to the table is `table-csv-data`.

~~~php
echo $reader->toHTML('table table-bordered table-hover');
~~~

### Transcoding the CSV

The conversion methods rely on the fact the document is UTF-8 encoded. If this is not the case, the recommended way to transcode your CSV in a UTF-8 compatible charset is to use the <a href="/filtering/">library stream filtering mechanism</a>.

When this is not possible you can fallback to using the `setEncondingFrom` and `getEncondingFrom` methods.

The CSV will be internally converted into UTF-8 prior to output using the `mb_string` extension.

~~~php
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
$reader->setEncodingFrom('iso-8859-15');
echo json_encode($reader); //the CSV is transcoded from iso-8859-15 to UTF-8;
echo $reader; //the CSV is not transcoded
~~~
