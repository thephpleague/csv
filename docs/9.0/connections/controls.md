---
layout: default
title: Csv character controls
---

# CSV character controls

To correctly parse a CSV document you are required to set the character controls to be used by the `Reader` or the `Writer` object.

## The delimiter character

### Description

```php
public AbstractCsv::setDelimiter(string $delimiter): self
public AbstractCsv::getDelimiter(void): string
```

### Example

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
```

<p class="message-info">The default delimiter character is <code>,</code>.</p>

<p class="message-warning"><code>setDelimiter</code> will throw a <code>Exception</code> exception if the submitted string length is not equal to <code>1</code> byte.</p>

## The enclosure character

### Description

```php
public AbstractCsv::setEnclosure(string $enclosure): self
public AbstractCsv::getEnclosure(void): string
```

### Example

```php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
```

<p class="message-info">The default enclosure character is <code>"</code>.</p>

<p class="message-warning"><code>setEnclosure</code> will throw a <code>Exception</code> exception if the submitted string length is not equal to <code>1</code> byte.</p>

## The escape character

This is a PHP specific control character which sometimes interferes with CSV parsing and writing.
It is recommended in versions preceding `9.2.0` to never change its default value unless you do understand its usage and its consequences.

<p class="message-warning">Starting with version <code>PHP7.4</code> it is recommended to use the library
with the escape parameter equal to the empty string.
see <a href="https://wiki.php.net/rfc/deprecations_php_8_4#deprecate_proprietary_csv_escaping_mechanism">Deprecation for PHP8.4</a> and
<a href="https://nyamsprod.com/blog/csv-and-php8-4/">CSV and PHP8.4</a> for more information.</p>

### Description

```php
public AbstractCsv::setEscape(string $escape): self
public AbstractCsv::getEscape(void): string
```

### Example

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
```

<p class="message-info">The default escape character is <code>\</code>.</p>
<p class="message-notice">Since version <code>9.2.0</code> you can provide an empty string for the escape character to enable better <a href="https://tools.ietf.org/html/rfc4180">RFC4180</a> compliance.</p>
<p class="message-warning"><code>setEscape</code> will throw a <code>Exception</code> exception if the submitted string length is not equal to <code>1</code> byte or an empty string.</p>

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setEscape('');
$escape = $csv->getEscape(); //returns ""
```

## Inherited character controls

When using a `SplFileObject`, the returned `AbstractCsv` object will inherit the object underlying CSV controls.

```php
$file = new SplTempFileObject();
$file->setFlags(SplFileObject::READ_CSV);
$file->setCsvControl('|', "'", "\\");

$csv = Reader::createFromFileObject($file);

echo $csv->getDelimiter(); //display '|'
echo $csv->getEnclosure(); //display "'"
echo $csv->getEscape();    //display '\'
```

## Detecting the delimiter character

```php
function League\Csv\Info::getDelimiterStats(Reader $csv, array $delimiters, int $limit = 1): array
```

or

```php
function League\Csv\delimiter_detect(Reader $csv, array $delimiters, int $limit = 1): array
```

<p class="message-warning">Since version <code>9.7</code> this function is deprecated and you are encouraged to use <code>Info::getDelimiterStats</code> instead.</p>

The `Info::getDelimiterStats` static method helps detect the possible delimiter character used by the CSV document. This function returns the number of CSV fields found in the document depending on the submitted delimiters given.

The function takes three (3) arguments:

- a [Reader](/9.0/reader/) object;
- an array containing the delimiters to check;
- an integer which represents the number of CSV records to scan (defaults to `1`);

and returns an associated array whose keys are the submitted delimiters characters and whose values represent the field numbers found depending on the delimiter value.

```php
use League\Csv\Info;
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$reader->setEnclosure('"');
$reader->setEscape('\\');

$result = Info::getDelimiterStats($reader, [' ', '|'], 10);
// $result can be the following
// [
//     '|' => 20,
//     ' ' => 0,
// ]
// This seems to be a consistent CSV with:
// - 20 fields were counted with the "|" delimiter in the 10 first records;
// - in contrast no field was detected for the " " delimiter;
```

If the submitted delimiter **is invalid or not found** in the document, `0` will be returned as its associated value.

<p class="message-info">To detect the delimiters stats on the full CSV document you need to set <code>$limit</code> to <code>-1</code>.</p>
<p class="message-notice">This function only returns hints. Only the CSV providers will validate the real CSV delimiter character.</p>
<p class="message-warning">This function only tests the delimiters you pass it.</p>
