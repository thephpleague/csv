---
layout: homepage
---

# Features

## Accessing CSV documents records

`Reader`, the read only connection object enables accessing CSV records easily

```php
use League\Csv\Reader;

//load the CSV document from a file path
$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$csv->setHeaderOffset(0);

$header = $csv->getHeader(); //returns the CSV header record

//returns all the records as
$records = $csv->getRecords(); // an Iterator object containing arrays
$records = $csv->getRecordsAsObject(MyDTO::class); //an Iterator object containing MyDTO objects

echo $csv->toString(); //returns the CSV document as a string
```

## Adding new CSV records is made simple

`Writer`, the write only connection object enables adding one or more records in one call.

```php
use League\Csv\Writer;

$header = ['first name', 'last name', 'email'];
$records = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    ['john', 'doe', 'john.doe@example.com'],
];

//load the CSV document from a string
$csv = Writer::createFromString();

//insert the header
$csv->insertOne($header);

//insert all the records
$csv->insertAll($records);

echo $csv->toString(); //returns the CSV document as a string
```

## Advanced CSV records selection

`Statement`, the constraint builder object ease CSV records selection

```php
use League\Csv\Reader;
use League\Csv\Statement;

//load the CSV document from a stream
$stream = fopen('/path/to/your/csv/file.csv', 'r');
$csv = Reader::createFromStream($stream);
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);

//build a statement
$stmt = Statement::create()
    ->offset(10)
    ->limit(25);

//query your records from the document
$records = $stmt->process($csv);
foreach ($records as $record) {
    //do something here
}
```

## CSV documents converters

Different converters objects ease transforming your CSV documents into other popular formats

```php
use League\Csv\Reader;
use League\Csv\XMLConverter;

//load the CSV document from a SplFileObject
$file = new SplFileObject('/path/to/your/csv/file.csv', 'r');
$csv = Reader::createFromFileObject($file);

$converter = XMLConverter::create()
    ->rootElement('csv')
    ->recordElement('record', 'offset')
    ->fieldElement('field', 'name');

$dom = $converter->convert($csv);
$dom->formatOutput = true;
$dom->encoding = 'iso-8859-15';

echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="iso-8859-15"?>
// <csv>
//   ...
//   <record offset="71">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">137</field>
//     <field name="sexe">F</field>
//     <field name="annee">2004</field>
//   </record>
//   ...
//   <record offset="1099">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">124</field>
//     <field name="sexe">F</field>
//     <field name="annee">2005</field>
//   </record>
// </csv>
```

## Supports PHP Stream filter API

PHP stream filters can directly be used to ease manipulating CSV document

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$csv->setHeaderOffset(0);

$input_bom = $csv->getInputBOM();

if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
    $csv->addStreamFilter('convert.iconv.UTF-16/UTF-8');
}

foreach ($csv as $record) {
    //all fields from the record are converted from UTF-16 into UTF-8 charset
    //and the BOM sequence is removed
}
```
