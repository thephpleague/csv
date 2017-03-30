---
layout: default
title: Converting a CSV into an HTML table
---

# HTML conversion

`HTMLConverter` converts a CSV records collection into a HTML Table by implementing the [Converter interface](/9.0/converter/#converter-interface).


## Settings

Prior to converting your records collection into a HTML table, you may wish to configure optional information to improve your table rendering.

<p class="message-warning">Because we are using internally the <a href="/9.0/converter/xml/">XMLConverter</a>, if an error occurs while validating the submitted values a <code>DOMException</code> exception will be thrown.</p>

### HTMLConverter::encoding

~~~php
<?php

public HTMLConverter::encoding(string $encoding): self
~~~

This method sets the HTML encoding charset which default to `UTF-8` if none is supplied.

### HTMLConverter::table

~~~php
<?php
public HTMLConverter::table(string $class_name, string $id_value = ''): self
~~~

This method sets:

- the optional table `class` attribute value, if none is uses it will default to `table-csv-data`;
- the optional table `id` attribute value;

### HTMLConverter::tr

~~~php
<?php
public HTMLConverter::tr(string $record_offset_attribute_name): self
~~~

This method sets the optional attribute name for the record offset on the HTML `tr` tag. If none is use or an empty string is given, the record offset information won't be exported to the HTML table

### HTMLConverter::td

~~~php
<?php
public HTMLConverter::td(string $fieldname_attribute_name): self
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
    ->table('table-csv-data', 'users')
    ->tr('data-record-offset')
    ->td('title')
;

// The PDOStatement Object implements the Traversable Interface
// that's why Converter::convert can directly insert
// the data into the HTML Table
$html = $converter->convert($sth);

echo $html;

// <table class="table-csv-data" id="users">
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

<p class="message-info"><strong>Tip:</strong> if needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>