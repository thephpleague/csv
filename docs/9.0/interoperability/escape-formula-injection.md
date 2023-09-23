---
layout: default
title: CSV Formula Injection
---

# Prevents CSV Formula Injection

<p class="message-notice">Available since version <code>9.1.0</code></p>

The `EscapeFormula` Formatter formats CSV records to reduce [CSV Formula Injection](http://georgemauer.net/2017/10/07/csv-injection.html) in imported Spreadsheet programs.

<p class="message-warning">Since version <code>9.7.4</code> the default values from the class constructor were updated to comply with the latest recommendations from OWASP regarding <a href="https://owasp.org/www-community/attacks/CSV_Injection" target="_blank">CSV injection</a>.
As this is a security fix, the BC break should be minimal.</p>

## Usage with CSV objects

The `EscapeFormula` class uses the formatter capabilities of the `Writer` object to escape formula injection.

```php
public function __construct(string $escape = "'", array $special_chars = [])
public function escapeRecord(array $record): array
public function unescapeRecord(array $record): array
```

<p><code>EscapeFormula::unescapeRecord</code> is available since version <code>9.11.0</code></p>

The `EscapeFormula::__construct` method takes two (2) arguments:

- the `$escape` parameter which will be used to prepend the record field, which defaults to `'`;
- the `$special_chars` parameter which is an `array` with additional characters that need to be escaped. By default, the following characters at the start of any record field content will be escaped `+`,`-`,`=`,`@`, `\t`, `\r`;
- for more information see [OWASP - CSV Injection](https://owasp.org/www-community/attacks/CSV_Injection)

```php
use League\Csv\EscapeFormula;
use League\Csv\Writer;

$formatter = new EscapeFormula();
$writer = Writer::createFromPath('php://temp', 'r+');
$writer->addFormatter($formatter->escapeRecord(...));
$writer->insertOne(['2', '2017-07-25', 'Important Client', '=2+5', 240, null]);
$writer->toString();
//outputting a CSV Document with all CSV Formula Injection escaped
//"2,2017-07-25,\"Important Client\",\"\t=2+5\",240,\n"
```

Conversely, if you obtain a CSV document containing escaped formula field you can use the `Esca[eFormula::unescapeRecord` to remove any escaping character.

```php
use League\Csv\EscapeFormula;
use League\Csv\Reader;

$formatter = new EscapeFormula();
$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->addFormatter($formatter->unescapeRecord(...))
$reader->first(); 
// returns ['2', '2017-07-25', 'Important Client', '=2+5', '240', '']
// the escaping characters are removed.
```

## Usage with PHP stream resources

You can use the `EscapeFormula` to format your records before calling `fputcsv` or `SplFileObject::fputcsv`.

```php
use League\Csv\EscapeFormula;

$resource = fopen('/path/to/my/file', 'r+');
$formatter = new EscapeFormula("`");
foreach ($iterable_data as $record) {
    fputcsv($resource, $formatter->escapeRecord($record));
}
```

Conversely, if you have a CSV document with escaped formula, you can access the original content using the new
`EscapeFormula::unescapeRecord` to remove any escaping character.

```php
use League\Csv\EscapeFormula;

$resource = fopen('/path/to/my/file', 'r');
$formatter = new EscapeFormula("`");
while (($data = fgetcsv($resource)) !== false) {
    $record = $formatter->unescapeRecord($data);
}
```

<p class="message-warning">Even though the <code>EscapeFormula</code> formatter is provided it must be stressed that
this is in no way a bulletproof method. This prevention mechanism only works if <strong>you know how the CSV export
will be consumed or have been generated</strong>. In any other cases, you are better off leaving the filtering
to the consuming client and report any found security concerns to their respective security channel.</p>
