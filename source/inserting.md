---
layout: layout
title: Reading & Filtering
---

# Inserting Data

To create or update a CSV use the following `League\Csv\Writer` methods.

<p class="message-warning">When inserting strings don't forget to specify the CSV delimiter and enclosure characters that match those use in the string.</p>

<p class="message-info">When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## Adding data to a CSV

### insertOne($data)

`insertOne` inserts a single row. This method can take an `array`, a `string` or
an `object` implementing the `__toString` method.

~~~.language-php
class ToStringEnabledClass
{
    private $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertOne("'john','doe','john.doe@example.com'"); 
$writer->insertOne(new ToStringEnabledClass("john,doe,john.doe@example.com")) 
~~~

### insertAll($data)

`insertAll` inserts multiple rows. This method can take an `array` or a 
`Traversable` object to add several rows to the CSV data.

~~~.language-php
$arr = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    "'john','doe','john.doe@example.com'",
    new ToStringEnabledClass("john,doe,john.doe@example.com")
];

$writer->insertAll($arr); //using an array 

$object = new ArrayIterator($arr);
$writer->insertAll($object); //using a Traversable object
~~~

## Handling null values

When importing data containing `null` values you should tell the library how to handle them. 

### setNullHandling($mode) *- since version 5.3*

To set the `Writer` class handling behavior, you will use the `setNullHandling` method. This method takes one of these constants mode:

* `Writer::NULL_AS_EXCEPTION`: Inserting methods throw a `RuntimeException` when a `null` value is found **the default behavior**;
* `Writer::NULL_AS_EMPTY`:Inserting methods convert `null` values into empty string;
* `Writer::NULL_AS_SKIP_CELL`: Inserting methods filter out each `null` item found;


~~~.language-php
$writer->setNullHandling(Writer::NULL_AS_SKIP_CELL);
$writer->insertOne(["one", "two", null, "four"]); 
~~~

In the above example, the `null` value will be filter out and the corresponding CSV row will contain only 3 items.

### getNullHandling() *- since version 5.3*

At any given time you are able to know the class mode using the `getNullHandling` method. By default the Writer mode to handle `null` value is `Writer::NULL_AS_EXCEPTION`, to keep the code backward compatible.

~~~.language-php
if (Writer::NULL_AS_EXCEPTION == $writer->getNullHandling()) {
    $writer->setNullHandling(Writer::NULL_AS_EMPTY);
}
$writer->insertOne(["one", "two", null, "four"]); 
~~~
In the above example, the `null` value will be converted into an empty string, only if the current mode handle the value by throwing exception.