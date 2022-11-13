---
layout: default
title: CSV document interoperability
---

# RFC4180 Field compliance

<p class="message-warning">This class is deprecated as of version <code>9.2.0</code>. Please use the <code>setEscape</code> method directly with the empty escape character argument instead with the <code>Reader</code> or the <code>Writer</code> object.</p>

The `RFC4180Field` class enables to work around the following bugs in PHP's native CSV functions:

- [bug #43225](https://bugs.php.net/bug.php?id=43225): `fputcsv` incorrectly handles cells ending in `\` followed by `"`
- [bug #55413](https://bugs.php.net/bug.php?id=55413): `str_getcsv` doesn't remove escape characters
- [bug #74713](https://bugs.php.net/bug.php?id=74713): CSV cell split after `fputcsv()` + `fgetcsv()` round trip.
- [bug #38301](https://bugs.php.net/bug.php?id=38301): field enclosure behavior in `fputcsv` (since version `9.1.0`)

When using this stream filter you can easily create or read a [RFC4180 compliant CSV document](https://tools.ietf.org/html/rfc4180#section-2) using `League\Csv` connections objects.

<p class="message-warning">Changing the CSV objects control characters <strong>after registering the stream filter</strong> may result in unexpected returned records.</p>

## Usage with League\CSV objects

```php
public static RFC4180Field::addTo(AbstractCsv $csv, string $whitespace_replace = ''): AbstractCsv
```

The `RFC4180Field::addTo` method will register the stream filter if it is not already the case and add the stream filter to the CSV object using the following properties:

- the CSV enclosure property;
- the CSV escape property;
- the CSV stream filter mode;

```php
use League\Csv\RFC4180Field;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp', 'r+');
$writer->setNewline("\r\n"); //RFC4180 Line feed
RFC4180Field::addTo($writer); //adding the stream filter to fix field formatting
$writer->insertAll($iterable_data);
$writer->output('mycsvfile.csv'); //outputting a RFC4180 compliant CSV Document
```

<p class="message-notice">the <code>$whitespace_replace</code> argument is available since version <code>9.1.0</code></p>

When the `$whitespace_replace` sequence is different from the empty space and does not contain:

- one of the current CSV control characters;
- a character that can trigger field enclosure;

its value will be used to:

- prevent `fputcsv` default behavior of always using enclosure when a whitespace is found in a record field

```php
use League\Csv\RFC4180Field;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp', 'r+');
RFC4180Field::addTo($writer, "\0");
$writer->insertOne(['foo bar', 'bar']);
echo $writer->getContent(); //display 'foo bar,bar' instead of '"foo bar",bar'
```

<p class="message-warning">The <code>$whitespace_replace</code> sequence should be a sequence not present in the inserted records, otherwise your CSV content will be affected by it.</p>

```php
use League\Csv\RFC4180Field;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp', 'r+');
RFC4180Field::addTo($writer, 'fo'); //incorrect sequence this will alter your CSV
$writer->insertOne(['foo bar', 'bar']);
echo $writer->getContent(); //display ' o bar,baz' instead of 'foo bar,baz'
```

### On records insertion

<p class="message-info">To fully comply with <code>RFC4180</code> you will also need to use <code>League\Csv\Writer::setNewline</code>.</p>

### On records extraction

Conversely, to read a RFC4180 compliant CSV document, when using the `League\Csv\Reader` object, you just need to add the `League\Csv\RFC4180Field` stream filter as shown below:

```php
use League\Csv\Reader;
use League\Csv\RFC4180Field;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$csv = Reader::createFromPath('/path/to/rfc4180-compliant.csv', 'r');
RFC4180Field::addTo($csv); //adding the stream filter to fix field formatting

foreach ($csv as $record) {
    //do something meaningful here...
}
```

## Usage with PHP stream resources

```php
public static RFC4180Field::register(): void
public static RFC4180Field::getFiltername(): string
```

To use this stream filter outside `League\Csv` objects you need to:

- register the stream filter using `RFC4180Field::register`.
- use `RFC4180Field::getFiltername` with one of PHP's attaching stream filter functions with the correct arguments as shown below:

```php
use League\Csv\RFC4180Field;

RFC4180Field::register();

$resource = fopen('/path/to/my/file', 'r');
$filter = stream_filter_append($resource, RFC4180Field::getFiltername(), STREAM_FILTER_READ, [
    'enclosure' => '"',
    'escape' => '\\',
    'mode' => STREAM_FILTER_READ,
]);

while (false !== ($record = fgetcsv($resource))) {
    //$record is correctly parsed
}
```
