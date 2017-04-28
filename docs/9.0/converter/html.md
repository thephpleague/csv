---
layout: default
title: Converting a CSV into an HTML table
---

# HTML conversion


~~~php
<?php

class HTMLConverter
{
    public function convert(iterable $records): string
    public function table(string $class_name, string $id_value = ''): self
    public function td(string $fieldname_attribute_name): self
    public function tr(string $record_offset_attribute_name): self
}
~~~

`HTMLConverter` converts a CSV records collection into a HTML Table using PHP's `DOMDocument` class.


## Settings

Prior to converting your records collection into a HTML table, you may wish to configure optional information to improve your table rendering.

<p class="message-warning">Because we are using internally the <a href="/9.0/converter/xml/">XMLConverter</a>, if an error occurs while validating the submitted values a <code>DOMException</code> exception will be thrown.</p>

### HTMLConverter::table

~~~php
<?php
public HTMLConverter::table(string $class_name, string $id_value = ''): self
~~~

This method sets the optional table `class` and `id` attribute values

<p class="message-info">The default <code>class</code> attribute value is <code>table-csv-data</code>.</p>
<p class="message-info">The default <code>id</code> attribute value is the empty string.</p>

### HTMLConverter::tr

~~~php
<?php
public HTMLConverter::tr(string $record_offset_attribute_name): self
~~~

This method sets the optional attribute name for the record offset on the HTML `tr` tag.

<p class="message-info">If none is use or an empty string is given, the record offset information won't be exported to the HTML table.</p>

### HTMLConverter::td

~~~php
<?php
public HTMLConverter::td(string $fieldname_attribute_name): self
~~~

This method sets the optional attribute name for the field name on the HTML `td` tag.

<p class="message-info">If none is use or an empty string is given, the field name information won't be exported to the HTML table.</p>

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

<p class="message-info">If needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>