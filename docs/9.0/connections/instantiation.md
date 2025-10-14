---
layout: default
title: Loading CSV documents
---

# Document loading

Because CSV documents come in different forms, we use named constructors to offer several ways to load them.

## Loading from a string

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

## Loading from a file or a stream

<p class="message-notice">This new API is introduced in version <code>9.27.0</code></p>

```php
public static AbstractCsv::from(
    SplFileInfo|SplFileObject|resource|string $filename,
    string $mode = 'r+',
    resource $context = null
): self
```

If an `string` or a `SplFileInfo` object is given as the `$filename` argument, a new instance
is created *à la* `fopen` and the `$mode` and the `$context` parameters are taking into account.

Otherwise, when a stream resource or an `SplFileObject` instance is given both arguments are
ignored.

<div class="message-notice">Since version <code>9.27.0</code> the following methods are <strong>deprecated</strong>:
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

## Accessing the CSV document path

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
