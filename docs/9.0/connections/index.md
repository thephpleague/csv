---
layout: default
title: CSV documents configurations
---

# Overview

## Connection type

Accessing the CSV document is done using one of the following classes:

- `League\Csv\Reader` to connect on a [read only mode](/9.0/reader/)
- `League\Csv\Writer` to connect on a [write only mode](/9.0/writer/)

Both classes extend the `League\Csv\AbstractCsv` class and as such share the following features:

- [Loading CSV document](/9.0/connections/instantiation/)
- [Setting up the CSV controls characters](/9.0/connections/controls/)
- [Managing the BOM sequence](/9.0/connections/bom/)
- [Adding PHP stream filters](/9.0/connections/filters/)
- [Outputting the CSV document](/9.0/connections/output/)

## OS specificity

If your CSV document was created or is read on a **Legacy Macintosh computer**, add the following lines before
using the library to help [PHP detect line ending](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (!ini_get('auto_detect_line_endings')) {
    ini_set('auto_detect_line_endings', '1');
}
```

<p class="message-warning"><code>auto_detect_line_endings</code> is deprecated since <code>PHP 8.1</code>; and will be removed in <code>PHP 9.0</code></p>

## Deprecation Notice

Starting with PHP8.4+, because the library is built using the native CSV features of PHP
your code may start emitting deprecation notices. To avoid those deprecations you
must explicitly set the escape control character to be the empty string as
shown in the example below:

```php
use League\Csv\Reader;
use League\Csv\Writer;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setEscape(''); //required in PHP8.4+

$writer = Writer::createFromString();
$writer->setEscape(''); //required in PHP8.4+
```

Doing so will prevent any deprecation notice. Of course, you are required to review on a
case-by-case basis these changes as they may introduce some parsing bugs while reading
existing CSV documents. A way to mitigate these issues is to re-encode your CSV
documents and strip away the deprecated escape mechanism all together. This can
be done using the script below:

```php
use League\Csv\Reader;
use League\Csv\Writer;

$csv = Reader::createFromPath('/path/to/file_with_escape_character.csv', 'r');
$writer = Writer::createFromPath('/path/to/file_without_escape_character.csv', 'w');
$writer->setEscape('');
$writer->setDelimiter($csv->getDelimiter()); //we re-use the old document character controls
$writer->setEnclosure($csv->getEnclosure()); //we re-use the old document character controls

$writer->insertAll($csv); // deprecation notice from reading the old file will be emitted!
```

This straightforward strategy using the package will re-encode your old CSV documents.
After that, you can use the new document by setting the escape character to the
empty string, and you will never get any deprecation notice again. And since
you created a new document it is easy to validate that your new document
still contains the same data.

Of course, it is highly recommended to adapt this script so that it can fit in
your application lifecycle.

## Exceptions

The default exception class thrown while using this library is `League\Csv\Exception` which extends PHP `Exception` class.

```php
use League\Csv\Exception;
use League\Csv\Reader;

try {
    $csv = Reader::createFromPath('/path/to/file.csv', 'r');
    $csv->setDelimiter('toto');
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
```

When using a non-seekable `SplFileObject`, a `RuntimeException` is thrown instead of a `League\Csv\Exception`
when using features that require a seekable CSV document. In the following example a seekable CSV document
is required to update the inserted end of line sequence.

```php
use League\Csv\Exception;
use League\Csv\Writer;

try {
    $csv = Writer::createFromFileObject(new SplFileObject('php://output', 'w'));
    $csv->setEndOfLine("\r\n");
    $csv->insertOne(["foo", "bar"]);
} catch (Exception | RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}

//in order to change the CSV document end of line a seekable CSV document is required
```
