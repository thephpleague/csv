---
layout: default
title: Loading CSV documents
description: The different ways the package allow to load and interact with CSV documents
---

# Document loading

Because CSV documents come in different forms, we use named constructors to offer several ways to load them.

## New API

### Loading from a string

<p class="message-notice">This new API is introduced in version <code>9.27.0</code></p>

```php
public static AbstractCsv::fromString(string $content = ''): self
```

Create a new object from a given string.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::fromString('john,doe,john.doe@example.com');
$writer = Writer::fromString('john,doe,john.doe@example.com');
```

<p class="message-notice">The <code>$content</code> argument default value is an empty string to ease usage.</p>

### Loading from a path

<div class="message-info">Since version <code>9.29.0</code></div>
<div class="message-notice">Since version <code>9.27.0</code> the <code>createFromPath()</code> method is <strong>deprecated</strong></div>

```php
public static Reader::fromPath(SplFileInfo|string $path, string $mode = 'r', ?resource $context = null): Reader
public static Writer::fromPath(SplFileInfo|string $path, string $mode = 'r+', ?resource $context = null): Writer
```

Creates a new object *à la* `fopen`.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::fromPath('/path/to/your/csv/file.csv', 'r');
$writer = Writer::fromPath(new SplFileInfo('/path/to/your/csv/file.csv'), 'w');
```

<p class="message-warning">A <code>SplFileObject</code> does not expose its context. If it was created with one, you must pass it explicitly to the <code>$context</code> argument.
Alternatively, you can use the <code>fromStream</code> method.</p>

### Loading from stream

<div class="message-info">Since version <code>9.29.0</code></div>
<div class="message-notice">Since version <code>9.27.0</code> the <code>createFromStream()</code> and <code>createFromFileObject()</code> methods are <strong>deprecated</strong></div>

```php
public static AbstractCsv::fromStream(SplFileObject|resource $stream): self
```

Creates a new object from a stream resource or a streaming object.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::fromStream(fopen('/path/to/the/file.csv', 'r+'));
$writer = Writer::fromStream(tmpfile());
$reader = Reader::fromStream(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::fromStream(new SplTempFileObject());
```

The provided stream—whether a resource or a SplFileObject—is used as-is. It is the developer’s responsibility to ensure that the stream
is valid and has the appropriate permissions; otherwise, exceptions may be thrown during use.

### Loading from a file pointer

<p class="message-notice">This new API is introduced in version <code>9.27.0</code></p>

```php
public static AbstractCsv::from(
    SplFileInfo|SplFileObject|resource|string $filename,
    string $mode = 'r+',
    resource $context = null
): self
```

If a `string` or a `SplFileInfo` object is given as the `$filename` argument, a new instance
is created *à la* `fopen` and the `$mode` and `$context` parameters are taken into account.

Otherwise, when a stream resource or an `SplFileObject` instance is given, both arguments are
ignored.

<div class="message-notice">Since version <code>9.27.0</code> this method can be use to replace the <strong>deprecated</strong> methods:
<ul>
    <li><code>createFromPath()</code></li>
    <li><code>createFromStream()</code></li>
    <li><code>createFromFileObject()</code></li>
</ul>
</div>

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::from('/path/to/your/csv/file.csv', 'r');
$writer = Writer::from('/path/to/your/csv/file.csv', 'w');

$reader = Reader::from(fopen('/path/to/the/file.csv', 'r+'));
$writer = Writer::from(tmpfile());

$reader = Reader::from(new SplFileInfo('/path/to/your/csv/file.csv'));
$writer = Writer::from(new SplTempFileObject());
```

<div class="message-notice">
The <code>$mode</code> argument defaults to:
<ul>
<li><code>r+</code> for the <code>Writer</code> class</li>
<li><code>r</code> for the <code>Reader</code> class</li>
</ul>
</div>

<p class="message-notice">The method allows loading non-seekable stream resource.</p>

## Legacy API

<p class="message-warning">The following methods are all deprecated as of version <code>9.27.0</code></p>
<p class="message-warning">Since version <code>9.1.0</code> non-seekable CSV documents can be used but <strong>exceptions will be thrown if features requiring a seekable CSV document are used.</strong></p>

### Loading from a string

```php
public static AbstractCsv::createFromString(string $content = ''): self
```

Creates a new object from a given string.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
```

<p class="message-notice">Since version <code>9.2.0</code> the <code>$content</code> argument default value is an empty string to ease usage.</p>

### Loading from a file path

```php
public static AbstractCsv::createFromPath(
    string $path,
    string $open_mode = 'r+',
    resource $context = null
): self
```

Creates a new object *à la* `fopen`.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$writer = Writer::createFromPath('/path/to/your/csv/file.csv', 'w');
```

<div class="message-notice">
Starting with version <code>9.1.0</code>, <code>$open_mode</code> defaults to:
<ul>
<li><code>r+</code> for the <code>Writer</code> class</li>
<li><code>r</code> for the <code>Reader</code> class</li>
</ul>
</div>

### Loading from a resource stream

```php
public static AbstractCsv::createFromStream(resource $stream): self
```

Creates a new object from a stream resource.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromStream(fopen('/path/to/the/file.csv', 'r+'));
$writer = Writer::createFromStream(tmpfile());
```

<p class="message-notice">Prior to version <code>9.1.0</code>, the method would throw a <code>League\Csv\Exception</code> for a non-seekable stream resource.</p>

### Loading from a SplFileObject object

```php
public static AbstractCsv::createFromFileObject(SplFileObject $file): self
```

Creates a new object from a `SplFileObject` object.

```php
use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject());
```

## Accessing the document path

<p class="message-notice">New in version <code>9.2.0</code></p>

```php
public AbstractCsv::getPathname(): string
```

Once instantiated, the `getPathname` method returns the pathname of the underlying document.

```php
use League\Csv\Reader;
use League\Csv\Writer;

Reader::from(new SplFileObject('/path/to/your/csv/file.csv'))->getPathname();
//returns '/path/to/your/csv/file.csv'
Writer::from(new SplTempFileObject())->getPathname();
//returns php://temp
```
