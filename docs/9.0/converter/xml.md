---
layout: default
title: Converting a CSV into a XML DOMDocument object
---

# XML conversion

The `XMLConverter` converts a CSV records collection into a PHP `DOMDocument`.

## Settings

Prior to converting your records collection into XML, you may wish to configure the element and its associated attribute names. To do so, `XMLConverter` provides methods to set up these settings.

<p class="message-warning">Because we are building a <code>DOMDocument</code> object, the <code>XMLConverter</code> object throws <code>DOMException</code> instead of <code>League\Csv\Exception</code>.</p>

### XMLConverter::rootElement

```php
public XMLConverter::rootElement(string $node_name): self
```

This method sets the XML root name.

<p class="message-info">The default root element name is <code>csv</code>.</p>

### XMLConverter::recordElement

```php
public XMLConverter::recordElement(string $node_name, string $record_offset_attribute_name = ''): self
```

This method sets the XML record name and optionally the attribute name for the record offset value if you want this information preserved.

<p class="message-info">The default record element name is <code>row</code>.</p>
<p class="message-info">The default attribute name is an empty string.</p>

### XMLConverter::fieldElement

```php
public XMLConverter::fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
```

This method sets the XML field name and optionally the attribute name for the field name value.

<p class="message-info">The default field element name is <code>cell</code>.</p>
<p class="message-info">The default attribute name is an empty string.</p>

## Conversion

```php
public XMLConverter::convert(iterable $records): DOMDocument
```

The `XMLConverter::convert` accepts an `iterable` which represents the records collection and returns a `DOMDocument` object.

```php
use League\Csv\XMLConverter;
use League\Csv\Statement;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/prenoms.csv', 'r');
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);

$stmt = (new Statement())
    ->where(function (array $record) {
        return 'Anaïs' === $record['prenoms'];
    })
    ->offset(0)
    ->limit(2)
;

$converter = (new XMLConverter())
    ->rootElement('csv')
    ->recordElement('record', 'offset')
    ->fieldElement('field', 'name')
;

$records = $stmt->process($csv);

$dom = $converter->convert($records);
$dom->formatOutput = true;
$dom->encoding = 'iso-8859-15';

echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="iso-8859-15"?>
// <csv>
//   <record offset="71">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">137</field>
//     <field name="sexe">F</field>
//     <field name="annee">2004</field>
//   </record>
//   <record offset="1099">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">124</field>
//     <field name="sexe">F</field>
//     <field name="annee">2005</field>
//   </record>
// </csv>
```

<p class="message-info">If needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>

## Import

<p class="message-info">New feature introduced in version <code>9.3.0</code></p>

```php
public XMLConverter::import(iterable $records, DOMDocument $doc): DOMElement
```

Instead of converting your tabular data into a full XML document you can now import it into an already existing `DOMDocument` object.
To do so, you need to specify which document the data should be imported into using the `XMLConverter::import` method.

This method takes two arguments:

- the tabular data as defined for the `XMLConverter::convert` method;
- a `DOMDocument` object to import the data into;

Note that the resulting `DOMElement` is attached to the given `DOMDocument` object but not yet included in the document tree.
To include it, you still need to call a DOM insertion method like `appendChild` or `insertBefore` with a node that *is* currently in the document tree.

```php
use League\Csv\XMLConverter;
use League\Csv\Statement;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/prenoms.csv', 'r');
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);

$stmt = (new Statement())
    ->where(function (array $record) {
        return 'Anaïs' === $record['prenoms'];
    })
    ->offset(0)
    ->limit(2)
;

$converter = (new XMLConverter())
    ->rootElement('csv')
    ->recordElement('record', 'offset')
    ->fieldElement('field', 'name')
;

$records = $stmt->process($csv);

$dom = new DOMDocument('1.0');
$dom->loadXML('<root><header><name>My CSV Document</name></header></root>');

$data = $converter->import($records, $dom);
$dom->appendChild($data);
$dom->formatOutput = true;
$dom->encoding = 'iso-8859-15';

echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="iso-8859-15"?>
// <root>
//   <header>
//     <name>My CSV Document</name>
//   </header>
//   <csv>
//     <record offset="71">
//       <field name="prenoms">Anaïs</field>
//       <field name="nombre">137</field>
//       <field name="sexe">F</field>
//       <field name="annee">2004</field>
//     </record>
//     <record offset="1099">
//       <field name="prenoms">Anaïs</field>
//       <field name="nombre">124</field>
//       <field name="sexe">F</field>
//       <field name="annee">2005</field>
//     </record>
//   </csv>
// </root>
```

## Download

<p class="message-warning">If you are using the package inside a framework please use the framework recommended way instead of the describe mechanism hereafter.</p>

To download the generated JSON you can use the `XMLConverter::download` method. The method returns
the total number of bytes sent just like the `XMLConverter::save` method and enable downloading the XML on the fly.

### General purpose

<p class="message-info">new in version <code>9.18.0</code></p>

```php
use League\Csv\Reader;
use League\Csv\JsonConverter;

$reader = Reader::createFromPath('file.csv');
$reader->setHeaderOffset(0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/xml; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.xml"');

XMLConverter::create()->download($reader);
die;
```

In this scenario, you have to specify all the headers for the file to be downloaded.

### Using a filename

<p class="message-info">new in version <code>9.17.0</code></p>

To download the generated XML on the fly you can use the `XMLConverter::download` method:

```php
use League\Csv\Reader;
use League\Csv\XMLConverter;

$reader = Reader::createFromPath('file.csv');
$reader->setHeaderOffset(0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
 //the filename will be the name of the downloaded xml as shown by your HTTP client!
XMLConverter::create()->download($reader, 'name-for-your-file.xml');
die;
```

<p class="message-notice">The caching headers are given as an example for using additional headers, it is up to the user to decide if those headers are needed or not.</p>

By default, the method will set the encoding to `utf-8` and will not format the XML. You can set those values using
the method optional arguments.

```php
use League\Csv\Reader;
use League\Csv\XMLConverter;

$reader = Reader::createFromPath('file.csv');
$reader->setHeaderOffset(0);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
XMLConverter::create()->download(
    records: $reader,
    filename: 'generated_file.xml',
    encoding: 'iso-8859-1',
    formatOutput: true,
);
die;
```

<p class="message-warning">No check is done on the validity of the <code>encoding</code> string provided.</p>
