---
layout: default
title: Converting your CSV
---

# Converting the CSV

The `League\Csv` object can convert your CSV document into JSON, XML and HTML formats. In order to do so, the conversion methods assume that your CSV is UTF-8 encoded. To properly transcode your document into an UTF-8 compatible charset, it's recommended to use the <a href="/filtering/">library stream filtering mechanism</a>.

When this is not possible/applicable you can fallback to using the `setEncodingFrom` and `getEncodingFrom` methods.

If your CSV is not UTF-8 encoded some unexpected results and some errors may be thrown when trying to convert your data.

## Convert to JSON format

`AbstractCsv` implements the `JsonSerializable` interface. As such you can use the `json_encode` function directly on the instantiated object.

~~~php
<?php

echo json_encode($reader);
~~~

## Convert to XML

Use the `toXML` method to convert the CSV data into a `DomDocument` object.

~~~php
<?php

public AbstractCsv::toXML(
    string $root_name = 'csv',
    string $row_name = 'row',
    string $cell_name = 'cell'
): DOMDocument
~~~

This method accepts 3 optionals arguments to help you customize the XML tree:

- `$root_name`, the XML root name which defaults to `csv`;
- `$row_name`, the XML node element representing a CSV row which defaults to `row`;
- `$cell_name`, the XML node element for each CSV cell which defaults value is `cell`;

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$dom = $reader->toXML('data', 'line', 'item');
~~~

## Convert to HTML table

Use the `toHTML` method to convert the CSV data into an HTML table.

~~~php
<?php

public AbstractCsv::toHTML(string $classAttribute = 'table-csv-data'): string
~~~

This method accepts an optional argument `$classAttribute` to help you customize the table
rendering. By defaut the classname given to the table is `table-csv-data`.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
echo $reader->toHTML('table table-bordered table-hover');
~~~

## Example using data transcode before conversion

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
//we are using the setEncodingFrom method to transcode the CSV into UTF-8
$reader->setEncodingFrom('iso-8859-15');
echo json_encode($reader);
//the CSV is transcoded from iso-8859-15 to UTF-8
//before being converted to JSON format;
echo $reader; //outputting the data is not affected by the conversion
~~~
