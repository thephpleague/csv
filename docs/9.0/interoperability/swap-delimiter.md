---
layout: default
title: Handling multibytes delimiter
---

# Multibyte delimiter

<p class="message-info">Available since version <code>9.13.0</code></p>

The `SwapDelimiter` is a PHP stream filter which enables converting the multibytes delimiter into a
suitable delimiter character to allow processing your CSV document.

## Usage with CSV objects

Out of the box, the package is not able to handle multibytes delimited CSV. You should first try to
see if by changing your PHP locale settings the CSV gets correctly parsed.

```php
use League\Csv\SwapDelimiter;
use League\Csv\Reader;

$document = <<<CSV
csv;content;in;japanese;locale
CSV;

setlocale(LC_ALL, 'ja_JP.SJIS');
$reader = Reader::createFromString($document);
$reader->setHeaderOffset(0);
$reader->first();
```

If that does not work you can then try using the `SwapDelimiter` stream filter.

```php
public static SwapDelimiter::addTo(AbstractCsv $csv, string $sourceDelimiter): void
```

The `SwapDelimiter::addTo` method will:

- register the stream filter if it is not already the case.
- add a stream filter using the specified CSV delimiter.
  - for the `Writer` object it will convert the CSV single-byte delimiter into the `$sourceDelimiter`
  - for the `Reader` object it will convert the `$sourceDelimiter` delimiter into a CSV single-byte delimiter

```php
use League\Csv\SwapDelimiter;
use League\Csv\Writer;

$writer = Writer::createFromString();
$writer->setDelimiter("\x02");
SwapDelimiter::addTo($writer, '💩');
$writer->insertOne(['toto', 'tata', 'foobar']);
$writer->toString();
//returns toto💩tata💩foobar\n
```

Once the `SwapDelimiter::addTo` is called you should not change your CSV `delimiter` setting. Or put in
other words. You should first set the CSV single-byte delimiter before calling the `SwapDelimiter` method.

Conversely, you can use the same technique with a `Reader` object.

```php
use League\Csv\SwapDelimiter;
use League\Csv\Reader;

$document = <<<CSV
observedOn💩temperature💩place
2023-10-01💩18💩Yamoussokro
2023-10-02💩21💩Yamoussokro
2023-10-03💩15💩Yamoussokro
CSV;

$reader = Reader::createFromString($document);
$reader->setHeaderOffset(0);
$reader->setDelimiter("\x02");
SwapDelimiter::addTo($reader, '💩');
$reader->first();
//returns  ['observedOn' => '2023-10-01', 'temperature' => '18', 'place' => 'Yamoussokro']
```

<p class="message-info">For the conversion to work the best you should use a single-byte CSV delimiter
which is not present in the CSV itself. Generally a good candidate is a character in the ASCII range from 1
to 32 included (excluding the end of line character).</p>

<p class="message-warning">The CSV document content is <strong>never changed or replaced</strong> when
reading an existing CSV. The conversion is only persisted during writing after all the formatting is done.</p>
