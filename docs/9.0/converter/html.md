---
layout: default
title: Converting a CSV into an HTML table
---

# HTML conversion

`HTMLConverter` converts a CSV records collection into a HTML Table by implementing the [Converter interface](/9.0/converter/#converter-interface) and using the [inputEncoding method](/9.0/converter/#records-input-encoding).


## Settings

### HTMLConverter::className

~~~php
<?php
public HTMLConverter::className(string $class_name): self
~~~

This method sets the optional table `class` attribute name. If none is uses it will default to `table-csv-data`.

### HTMLConverter::recordOffsetAttributeName

~~~php
<?php
public HTMLConverter::recordOffsetAttribute(string $attribute_name): self
~~~

This method sets the optional attribute name for the record offset on the HTML `tr` tag. If none is use or an empty string is given, the record offset information won't be exported to the HTML table

### HTMLConverter::fieldAttributeName

~~~php
<?php
public HTMLConverter::fieldAttributeName(string $attribute_name): self
~~~

This method sets the optional attribute name for the field name on the HTML `td` tag. If none is use or an empty string is given, the field name information won't be exported to the HTML table;

## Conversion

~~~php
<?php
public HTMLConverter::convert(iterable $records): string
~~~

The `HTMLConverter::convert` accepts an `iterable` which represents the records collection and returns a string.

~~~php
<?php

use League\Csv\HTMLConverter;

//we fetch the info from a DB using a PDO object
$sth = $dbh->prepare("SELECT firstname, lastname, email FROM users LIMIT 2");
$sth->setFetchMode(PDO::FETCH_ASSOC);
$sth->execute();

$converter = (new HTMLConverter())
    ->recordOffsetAttributeName('data-record-offset')
    ->fieldAttributeName('title')
;

// The PDOStatement Object implements the Traversable Interface
// that's why Converter::convert can directly insert
// the data into the HTML Table
$html = $converter->convert($sth);

echo $html;

// <table class="table-csv-data">
// <tr data-record-offset="0">
// <td title="firstname">john</td>
// <td title="lastname">doe</td>
// <td title="email">john.doe@example.com</td>
// </tr>
// <tr data-record-offset="1">
// <td title="firstname">jane</td>
// <td title="lastname">doe</td>
// <td title="email">jane.doe@example.com</td>
// </tr>
// </table>
~~~
