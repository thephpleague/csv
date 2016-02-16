---
layout: default
title: Converting your CSV
---

# Converting the CSV

the `League\Csv` object can convert your CSV document into JSON, XML and HTML format. In order to do so, the conversion methods assume that your CSV is UTF-8 encoded. To properly transcode your document into an UTF-8 compatible charset the recommended way is to use the <a href="/7.0/filtering/">library stream filtering mechanism</a>.

When this is not possible/applicable you can fallback to using the `setEncodingFrom` and `getEncodingFrom` methods.

If your CSV is not UTF-8 encoded some unexpected results and some errors could be thrown when trying to convert your data.

<p class="message-notice">Starting with <code>version 7.0</code>, when used with the <code>League\Csv\Reader</code> class, the conversion methods behavior are affected by the query options methods. Please refer to the <a href="/7.0/query-filtering">Query Filters page</a> for more informations and examples.</p>

<p class="message-notice">Starting with <code>version 7.1</code>,  query filters are also available for conversion methods when using the <code>League\Csv\Writer</code> class</p>

## Convert to JSON format

Use the `json_encode` function directly on the instantiated object.

~~~php
<?php

echo json_encode($reader);
~~~

## Convert to XML

Use the `toXML` method to convert the CSV data into a `DomDocument` object. This
method accepts 3 optionals arguments to help you customize the XML tree:

- `$root_name`, the XML root name which defaults to `csv`;
- `$row_name`, the XML node element representing a CSV row which defaults to `row`;
- `$cell_name`, the XML node element for each CSV cell which defaults value is `cell`;

~~~php
<?php

$dom = $reader->toXML('data', 'line', 'item');
~~~

## Convert to HTML table

Use the `toHTML` method to convert the CSV data into an HTML table. This method
accepts an optional argument `$classname` to help you customize the table
rendering, by defaut the classname given to the table is `table-csv-data`.

~~~php
<?php

echo $reader->toHTML('table table-bordered table-hover');
~~~

## Example using data transcode before conversion

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
