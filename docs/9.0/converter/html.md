---
layout: default
title: Converting a CSV into an HTML table
---

# HTML conversion

The `HTMLConverter` converts a CSV records collection into an HTML Table using PHP's `DOMDocument` class.

## Settings

Prior to converting your records collection into an HTML table, you may wish to configure optional information to improve your table rendering.

<p class="message-warning">Because we are using the <a href="/9.0/converter/xml/">XMLConverter</a> internally, if an error occurs while validating the submitted values, a <code>DOMException</code> exception will be thrown.</p>

### HTMLConverter::table

```php
public HTMLConverter::table(string $class_name, string $id_value = ''): self
```

This method sets the optional table `class` and `id` attribute values.

<p class="message-info">The default <code>class</code> attribute value is <code>table-csv-data</code>.</p>
<p class="message-info">The default <code>id</code> attribute value is an empty string.</p>

### HTMLConverter::tr

```php
public HTMLConverter::tr(string $record_offset_attribute_name): self
```

This method sets the optional attribute name for the record offset on the HTML `tr` tag.

<p class="message-info">If none is used or an empty string is given, the record offset information won't be exported to the HTML table.</p>

### HTMLConverter::td

```php
public HTMLConverter::td(string $fieldname_attribute_name): self
```

This method sets the optional attribute name for the field name on the HTML `td` tag.

<p class="message-info">If none is used or an empty string is given, the field name information won't be exported to the HTML table.</p>

## Conversion

<p class="message-info">Since version <code>9.3.0</code> this method accepts optional header and footer records to display them in the exported HTML table.</p>

```php
public HTMLConverter::convert(iterable $records, array $header_record = [], array $footer_record = []): string
```

The `HTMLConverter::convert` accepts an `iterable` which represents the records collection and returns a string.
It optionally accepts:

- an array of strings representing the tabular header;
- an array of strings representing the tabular footer;

If any of these arrays are present and non-empty, the tabular data will be contained in a `tbody` tag as per HTML specification.

```php
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


$html = $converter->convert($sth, ['First Name', 'Last Name', 'E-mail']);

echo $html;

// <table class="table-csv-data" id="users">
// <thead>
// <tr>
// <th scope="col">First Name</th>
// <th scope="col">Last Name</th>
// <th scope="col">E-mail</th>
// </tr>
// </thead>
// <tbody>
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
// </tbody>
// </table>
```

<p class="message-info">If needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>
