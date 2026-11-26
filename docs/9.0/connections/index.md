---
layout: default
title: CSV documents configurations
description: The different ways to access CSV documents
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

`League\Csv` relies on PHP's native CSV handling capabilities. As PHP evolves, some of these native features are being
deprecated, which may cause your application to emit deprecation notices when using the library.

To avoid these notices and prepare your application for future PHP versions, review the following recommendations.

### SplFileObject usage

Starting with PHP8.6+, avoid passing `SplFileObject` or `SplTempFileObject` instances to the library.

PHP has deprecated the CSV-related functionality exposed by `SplFileObject`, with its removal planned for `PHP 9`.
The library temporarily retains support for these objects to give users time to migrate their applications.

Instead, use PHP streams directly.

**For temporary files:**

```diff
- $csv = Writer::fromStream(new SplTempFileObject());
+ $csv = Writer::fromStream(tmpfile());
```

**For files on disk:**

```diff
- $csv = Reader::fromStream(new SplFileObject('path/to/file.csv', 'r'));
+ $csv = Writer::fromPath('path/to/file.csv', 'r');
```

The recommended migration path is to replace `SplFileObject` usage with native PHP stream resources or the library's
path-based constructors.

If you do not control how a `SplFileObject` instance is obtained, you can still use `fromPath()` as an intermediate
migration step:

```diff
- $csv = Reader::fromStream(new SplFileObject('path/to/file.csv', 'r'));
+ $csv = Writer::fromPath(new SplFileObject('path/to/file.csv', 'r'));
```

When given a `SplFileObject`, `fromPath()` resolves its underlying pathname using `SplFileObject::getPathname()` and opens
a new stream to access the file. The original object is therefore not used for CSV processing.

### Escape character usage

Starting with `PHP 8.4`, PHP deprecates calls to its native CSV functions that do not explicitly declare an escape character.
An escape character must now be provided, even when you intend to disable escaping by passing an empty string ('').

`League\Csv` explicitly configures the escape character when interacting with PHP's native CSV functions,
so using the library does not trigger these deprecation notices.

## Exceptions

The default exception class thrown while using this library is `League\Csv\Exception` which extends PHP `Exception` class.

```php
use League\Csv\Exception;
use League\Csv\Reader;

try {
    $csv = Reader::fromPath('/path/to/file.csv');
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
    $csv = Writer::fromStream(new SplFileObject('php://output', 'w'));
    $csv->setEndOfLine("\r\n");
    $csv->insertOne(["foo", "bar"]);
} catch (Exception | RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}

//in order to change the CSV document end of line a seekable CSV document is required
```
