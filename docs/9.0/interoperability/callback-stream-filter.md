---
layout: default
title: Dynamic Stream Filter
---

# Callback Stream Filter

<p class="message-info">Available since version <code>9.22.0</code></p>

Sometimes you may encounter a scenario where you need to create a specific stream filter
to resolve a specific issue. Instead of having to put up with the hassle of creating a
fully fledge stream filter, we are introducing a `CallbackStreamFilter`. This filter
is a PHP stream filter which enables applying a callable onto the stream prior to it
being actively consumed by the CSV process.

## Usage with CSV objects

Out of the box, the filter can not work, it requires a unique name and a callback to be usable.
Once registered you can re-use the filter with CSV documents or with a resource.

let's imagine we have a CSV document with the return carrier character as the end of line character.
This type of document is parsable by the package but only if you enable the deprecated `auto_detect_line_endings`.

If you no longer want to rely on that feature since it emits a deprecation warning you can use the new
`CallbackStreamFilter` instead by swaping the offending character with a modern alternative.

```php
use League\Csv\CallbackStreamFilter;
use League\Csv\Reader;

$csv = "title1,title2,title3\rcontent11,content12,content13\rcontent21,content22,content23\r";

$document = Reader::createFromString($csv);
CallbackStreamFilter::addTo(
    $document,
    'swap.carrier.return',
    fn (string $bucket): string => str_replace("\r", "\n", $bucket)
);
$document->setHeaderOffset(0);
return $document->first();
// returns ['title1' => 'content11', 'title2' => 'content12', 'title3' => 'content13']
```

The `addTo` method register the filter with the unique `swap.carrier.return` name and then attach
it to the CSV document object on read.

<p class="message-warning">On read, the CSV document content is <strong>never changed or replaced</strong>.
Conversely, the changes <strong>are persisted during writing</strong>.</p>

Of course the `CallbackStreamFilter` can be use in other different scenario or with PHP stream resources.
