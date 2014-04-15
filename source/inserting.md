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

## Handling null values (since version 5.3)

When importing data containing `null` values you should tell the library how to handle them. 

### setNullHandlingMode($mode)

To set the `Writer` class handling behavior, you will use the `setNullHandlingMode` method. This method takes one of these constants mode:

* `Writer::NULL_AS_EXCEPTION`: Inserting methods throw an `InvalidArgumentException` when a `null` value is found;
* `Writer::NULL_AS_EMPTY`:Inserting methods convert `null` values into empty string;
* `Writer::NULL_AS_SKIP_CELL`: Inserting methods filter out each `null` item found;

<p class="message-warning">By default the Writer mode to handle <code>null</code> value is <code>Writer::NULL_AS_EXCEPTION</code> to keep the code backward compatible.</p>

~~~.language-php
$writer->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
$writer->insertOne(["one", "two", null, "four"]); 
~~~

In the above example, the `null` value will be filter out and the corresponding CSV row will contain only 3 items.

### getNullHandlingMode()

At any given time you are able to know the class mode using the `getNullHandlingMode` method.

~~~.language-php
if (Writer::NULL_AS_EXCEPTION == $writer->getNullHandlingMode()) {
    $writer->setNullHandlingMode(Writer::NULL_AS_EMPTY);
}
$writer->insertOne(["one", "two", null, "four"]); 
~~~
In the above example, the `null` value will be converted into an empty string, only if the current mode handles the value by throwing exception.

## Handling CSV columns count consistency (since version 5.4)

You can optionally asks the Writer class to check the columns count consistency of the newly added rows in you CSV. To do so you need to set the class `$columns_count` property.

### getColumnsCount()

At any given time you can access the `$columns_count` property using the `getColumnsCount` method. 

<p class="message-warning">By default and for backward compatibility, the <code>$columns_count</code>property equals <code>-1</code> which means that column count consistency is not checked.</p>

### setColumnsCount($value)

One way to do enable the columns count check is to use the `setColumnsCount` method to set the property to an integer greater than `-1`.

~~~.language-php
$writer->setColumnsCount(2);
$nb_column_count = $writer->getColumnsCount(); // equals to 2;
$writer->insertAll([
    ["one", "two"],
    ["one", "two", "four"],  //this will throw an InvalidArgumentException
]); 
~~~

### autodetectColumnsCount()

Another way is to use the `autodetectColumnsCount` method which will set the `$columns_count` property according to the next inserted row.

~~~.language-php
$writer = new \League\Csv\Writer('path/to/csv', 'w');
$writer->autodetectColumnsCount();
$nb_column_count = $writer->getColumnsCount(); // equals to -1 = default value;
$writer->insertOne(["one", "two", "four"]); 
$nb_column_count = $writer->getColumnsCount(); // equals to 3;
$writer->insertOne(["one", "two"]); //this will throw an InvalidArgumentException
~~~

Keep in mind that:

* the effect of the `autodetectColumnsCount` method will only take place after the next call to `insertOne`.
* `setColumnsCount` and `autodetectColumnsCount` override each other effect when called before `insertOne`;
