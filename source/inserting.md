---
layout: layout
title: Reading & Filtering
---

# Inserting Data

To create or update a CSV use the following `League\Csv\Writer` methods.

<p class="message-warning">When inserting strings don't forget to specify the CSV delimiter and enclosure characters that match those use in the string.</p>

<p class="message-info">When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## insertOne

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

## insertAll

insertAll inserts multiple rows. This method can take an `array` or a 
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