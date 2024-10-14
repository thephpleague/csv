---
layout: default
title: CSV document output
---

# CSV document output

Once your CSV document is loaded, you can print or enable downloading it using the methods below.

The output methods **are affected by** [the output BOM sequence](/9.0/connections/bom/) and/or the supplied [PHP stream filters](/9.0/connections/filters/).

<p class="message-info">Even though you can use the following methods with the <code>League\Csv\Writer</code> object. It is recommended to do so with the <code>League\Csv\Reader</code> class to avoid losing the file cursor position and getting unexpected results when inserting new data.</p>

## Printing the document

Returns the string representation of the CSV document.

```php
public AbstractCsv::toString(void): string
public AbstractCsv::getContent(void): string
public AbstractCsv::__toString(void): string
```

<p class="message-notice">The <code>toString</code> method is added in version <code>9.7.0</code> and replaces the <code>getContent</code> method which is <strong>deprecated</strong>.</p>
<p class="message-notice">The <code>getContent</code> method is added in version <code>9.1.0</code> and replaces the <code>__toString</code> method which is <strong>deprecated</strong>.</p>

Use the `toString` method to return the full CSV content.

### Example

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
echo $reader->toString();
```

### Exceptions and Errors

If the CSV document is not seekable a `Exception` or a `RuntimeException` may be thrown when using `getContent`. A `Fatal Error` will be trigger when using the `__toString` method.

#### Using the `toString` method

```php
use League\Csv\Writer;

$csv = Writer::createFromFileObject(new SplFileObject('php://output', 'w'));
$csv->insertOne(['foo', 'bar']);
echo $csv->toString();
//throws a RuntimeException because the SplFileObject is not seekable
```

#### Using the `__toString` or `getContent` methods

```php
use League\Csv\Writer;

$csv = Writer::createFromFileObject(new SplFileObject('php://output', 'w'));
$csv->insertOne(['foo', 'bar']);
echo $csv;
echo $csv->getContent();
//throws a Fatal Error because no exception can be thrown by the __toString method
```

<p class="message-warning">The <code>__toString</code> method is deprecated in version <code>9.1.0</code> and will be removed in the next major version.</p>

## Downloading the document

<p class="message-warning">starting with version <code>9.18.0</code>, the <code>download</code> method replaces the <code>output</code> method which is deprecated.</p>

To make your CSV document downloadable use the `output` method to force the use of the output buffer on the CSV content.

```php
public AbstractCsv::download(?string $filename = null): int
```

The method returns the number of characters read from the handle and passed through to the output.

The output method can take an optional argument `$filename`. When present you can even remove more headers.

### Default usage

```php
use League\Csv\Reader;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
$reader->download();
die;
```

### Using the $filename argument

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('file.csv');
$reader->download('name-for-your-file.csv');
die;
```

<p class="message-notice">If you just need to make the CSV downloadable, end your script with a call to <code>die</code> just after the <code>output</code> method. You <strong>should not</strong> return the method returned value.</p>

## Outputting the document into chunks

```php
public AbstractCsv::chunk(int $length): Generator
```

The `AbstractCsv::chunk` method takes a single `$length` parameter specifying the number of bytes to read from the CSV document and returns a `Generator` to ease outputting large CSV files.

<p class="message-warning">If the <code>$length</code> parameter is not a positive integer an <code>OutOfRangeException</code> will be thrown.</p>

```php
use League\Csv\Reader;

header('Transfer-Encoding: chunked');
header('Content-Encoding: none');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Description: File Transfer');
header('Content-Disposition: attachment; filename="name-for-your-file.csv"');

$reader = Reader::createFromPath('/path/to/huge/file.csv', 'r');
foreach ($reader->chunk(1024) as $chunk) {
    echo dechex(strlen($chunk))."\r\n$chunk\r\n";
}
echo "0\r\n\r\n";
```

## Using a Response object (Symfony, Laravel, PSR-7 etc)

To avoid breaking the flow of your application, you should create a Response object when applicable in your framework. The actual implementation will differ per framework, but you should generally not output headers directly.

```php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
return new Response($reader->toString(), 200, [
    'Content-Encoding' => 'none',
    'Content-Type' => 'text/csv; charset=UTF-8',
    'Content-Disposition' => 'attachment; filename="name-for-your-file.csv"',
    'Content-Description' => 'File Transfer',
]);
```

In some cases you can also use a Streaming Response for larger files.
The following example uses Symfony's [StreamedResponse](https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response) object.

<p class="message-notice"><i>Be sure to adapt the following code to your own framework/situation. The following code is given as an example without warranty of it working out of the box.</i></p>

```php
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

//we generate the CSV using the Writer object
//$dbh is a PDO object
$stmt = $dbh->prepare("SELECT * FROM users");
$stmt->setFetchMode(PDO::FETCH_ASSOC);
$stmt->execute();
$csv = Writer::createFromPath('php://temp', 'r+');
$csv->insertAll($stmt);

//we create a callable to output the CSV in chunks
//with Symfony StreamResponse you can flush the body content if necessary
//see Symfony documentation for more information
$flush_threshold = 1000; //the flush value should depend on your CSV size.
$content_callback = function () use ($csv, $flush_threshold) {
    foreach ($csv->chunk(1024) as $offset => $chunk) {
        echo $chunk;
        if ($offset % $flush_threshold === 0) {
            flush();
        }
    }
};

//we send the CSV using Symfony StreamedResponse
$response = new StreamedResponse();
$response->headers->set('Content-Encoding', 'none');
$response->headers->set('Content-Type', 'text/csv; charset=UTF-8');

$disposition = $response->headers->makeDisposition(
    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
    'name-for-your-file.csv'
);

$response->headers->set('Content-Disposition', $disposition);
$response->headers->set('Content-Description', 'File Transfer');
$response->setCallback($content_callback);
$response->send();
```

### Notes

The output methods **can only be affected by**:

- the [library stream filtering mechanism](/9.0/connections/filtering/)
- the [BOM property](/9.0/connections/bom/)

No other method or property has an effect on them.
