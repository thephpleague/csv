---
layout: default
title: Basic Usage
---

# Basic usage

<p class="message-info"><strong>Tips:</strong> Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid loosing the file cursor position and getting unexpected results when inserting new data.</p>

Once your CSV object is [instantiated](/instantiation) and [configured](/properties/), you can start interacting with the data using a number of methods available to you. For starter, you can iterate over your newly object to extract each CSV row using the `foreach` construct.

~~~php
$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
foreach ($reader as $index => $row) {
    //do something meaningfull here with $row !!
    //$row is an array where each item represent a CSV data cell
    //$index is the CSV row index
}
~~~

<p class="message-notice">You can do more complex iterations <a href="/reading/">using the query methods</a> available on the <code>League\Csv\Reader</code> class only.</a></p>

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

<p class="message-notice"> Since <code>version 7.0</code>, the method returns the number of characters read from the handle and passed through to the output.</p>

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


The output methods **can only be affected by:**

- the [library stream filtering mechanism](/filtering/)
- the [BOM property](/bom/)

No other method or property have effect on them.

## Converting the CSV

the `League\Csv` object can convert your CSV document into JSON, XML and HTML format. In order to do so, the conversion methods assume that your CSV is UTF-8 encoded. To properly transcode your document into an UTF-8 compatible charset the recommended way is to use the <a href="/filtering/">library stream filtering mechanism</a>.

When this is not possible/applicable you can fallback to using the `setEncondingFrom` and `getEncondingFrom` methods.

If your CSV is not UTF-8 encoded some unexpected results and some errors could be thrown when trying to convert your data.

<p class="message-notice">Starting with <code>version 7.0</code>, when used with the <code>League\Csv\Reader</code> class, the conversion methods behavior are affected by the query options methods. Please refer to the <a href="/reading/#querying-csv-data">extracting data section</a> for more informations and examples.</p>

<p class="message-warning">To align with a bugfix in <code>SplFileObject</code>, since <code>version 7.1</code>, the <code>setFlags</code> method has no effect on the conversion mechanism to guarantee a valid conversion. Invalid rows are automatically skipped while converting the CSV document.</p>

### Convert to JSON format

Use the `json_encode` function directly on the instantiated object.

~~~php
echo json_encode($reader);
~~~

### Convert to XML

Use the `toXML` method to convert the CSV data into a `DomDocument` object. This
method accepts 3 optionals arguments to help you customize the XML tree:

- `$root_name`, the XML root name which defaults to `csv`;
- `$row_name`, the XML node element representing a CSV row which defaults to `row`;
- `$cell_name`, the XML node element for each CSV cell which defaults value is `cell`;

~~~php
$dom = $reader->toXML('data', 'line', 'item');
~~~

### Convert to HTML table

Use the `toHTML` method to convert the CSV data into an HTML table. This method
accepts an optional argument `$classname` to help you customize the table
rendering, by defaut the classname given to the table is `table-csv-data`.

~~~php
echo $reader->toHTML('table table-bordered table-hover');
~~~

### Example using data transcode before conversion

~~~php
$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
//we are using the setEncodingFrom method to transcode the CSV into UTF-8
$reader->setEncodingFrom('iso-8859-15');
echo json_encode($reader);
//the CSV is transcoded from iso-8859-15 to UTF-8
//before being converted to JSON format;
echo $reader; //outputting the data is not affected by the conversion
~~~
