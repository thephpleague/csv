---
layout: default
title: Deserializing a Tabular Data record into an object
---

# Mapping records to objects

<p class="message-notice">New in version <code>9.12.0</code></p>

## Converting an array to an object

If you prefer working with objects instead of typed arrays it is possible to map each record to
a specified class. To do so a new `Serializer` class is introduced to expose a deserialization mechanism

The class exposes three (3) methods to ease `array` to `object` conversion in the context of Tabular data:

- `Serializer::deserialize` which expect a single record as argument and returns on success an instance of the class.
- `Serializer::deserializeAll` which expect a collection of records and returns a collection of class instances.
- and the public static method `Serializer::map` which is a quick way to declare and convert a single record into an object.

```php
use League\Csv\Serializer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '-1.5',
    'place' => 'Berkeley',
];

$serializer = new Serializer(Weather::class, array_keys($record));
$weather = $serializer->deserialize($record);

$collection = [$record];
foreach ($serializer->deserializeAll($collection) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}

//this is equivalent to the first example
$weather = Serializer::map(Weather::class, $record);
```

If you are working with a class which implements the `TabularDataReader` interface you can use this functionality
directly by calling the `TabularDataReader::map` method.

Here's an example using the `Reader` class:

```php
use League\Csv\Reader;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getObjects(Weather::class) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

In the following sections we will explain the conversion and how it can be configured.

## Pre-requisite

The deserialization mechanism works mainly with DTO or objects which can be built
without complex logic.

<p class="message-notice">The mechanism relies on PHP's <code>Reflection</code>
features. It does not use the class constructor to perform the conversion.
This means that if the targeted object contains additional logic in its constructor,
the mechanism may either fail or produced unexpected results.</p>

To work as intended the mechanism expects the following:

- A target class where the array will be deserialized in;
- informations on how to convert cell value into object properties using dedicated attributes;

As an example if we assume we have the following CSV document:

```csv
date,temperature,place
2011-01-01,1,Galway
2011-01-02,-1,Galway
2011-01-03,0,Galway
2011-01-01,6,Berkeley
2011-01-02,8,Berkeley
2011-01-03,5,Berkeley
```

We can define a PHP DTO using the following class and the attributes.

```php
<?php

use League\Csv\Serializer\Cell;

final readonly class Weather
{
    public function __construct(
        public float $temperature,
        public Place $place,
        #[Cell(castArguments: ['format' => '!Y-m-d'])]
        public DateTimeImmutable $date;
    ) {
    }
}

enum Place
{
    case Berkeley;
    case Galway;
}
```

To get instances of your object, you now can call the `Serializer::deserialize` method as show below:

```php
use League\Csv\Reader;
use League\Csv\Serializer

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
$serializer = new Serializer(Weather::class, $csv->header());
foreach ($serializer->deserializeAll($csv) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

## Defining the mapping rules

By default, the deserialization engine will convert public properties using their name. In other words,
if there is a class property, which name is the same as a column name, the column value will be assigned
to this property. The appropriate type is used if the record cell value is a `string` or `null` and
the object public properties ares typed with

- `null`
- `mixed`
- a scalar type (support for `true` and `false` type is also present)
- any `Enum` object (backed or not)
- `DateTime`, `DateTimeImmuntable` and any class that extends those two classes.
- an `array`

When converting to a date object you can fine tune the conversion by optionally specifying the date
format and timezone. You can do so using the `Cell` attribute. This attribute will override the automatic
resolution and enable fine-tuning type casting on the property level.

```php
use League\Csv\Serializer;
use Carbon\CarbonImmutable;

#[Serializer\Cell(
    offset:'date',
    cast:Serializer\CastToDate::class,
    castArguments: [
        'format' => '!Y-m-d',
        'timezone' => 'Africa/Nairobi'
    ])
]
public CarbonImmutable $observedOn;
```

The above rule can be translated in plain english like this:

> convert the value of the associative array named `date` into a `CarbonImmutable` object
> using the date format `!Y-m-d` and the `Africa/Nairobi` timezone. Once created,
> inject the date instance into the `observedOn` property of the class.

The `Cell` attribute can be used:

- on class properties and methods (public, protected or private).

The `Cell` attribute can take up to three (3) arguments which are all optional:

- The `offset` argument which tell the engine which cell to use via its numeric or name offset. If not present  
the property name or the name of the first argument of the `setter` method will be used. In such case,  
you are required to specify the property names information.
- The `cast` argument which accept the name of a class implementing the `TypeCasting` interface and responsible  
for type casting the cell value.
- The `castArguments` which enable controlling typecasting by providing extra arguments to the `TypeCasting` class constructor

In any cases, if type casting fails, an exception will be thrown.

## Type casting the record value

The library comes bundles with four (4) type casting classes which relies on the property type information. All the
built-in methods support the `nullable` type. They will return `null` if the cell value is the empty string or `null`
only if the type is considered to be `nullable` otherwise they will throw an exception.
All classes are defined under the `League\Csv\Serializer` namespace.

### CastToBuiltInType

Converts the array value to a scalar type or `null` depending on the property type information. This class has no
specific configuration but will work with all the scalar type, the `true`, `null` and `false` value type as well as
with the `mixed` type. Type casting is done using the `filter_var` functionality of the `ext-filter` extension.

### CastToEnum

Convert the array value to a PHP `Enum` it supported both "real" and backed enumeration. No configuration is needed
if the value is not recognized an exception will be thrown.

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify the date format and its timezone if needed.

### CastToArray

Converts the value into a PHP `array`. You are required to specify what type of conversion you desired (`list`, `json` or `csv`).

The following are example for each type:

```php
$array['field1'] = "1,2,3,4";         //the string contains only a separator (type list)
$arrat['field2'] = '"1","2","3","4"'; //the string contains delimiter and enclosure (type csv)
$arrat['field3'] = '{"foo":"bar"}';   //the string is a json string (type json)
```

in case of

- the `list` type you can configure the `delimiter`, by default it is the `,`;
- the `csv` type you can configure the `delimiter` and the `enclosure`, by default they are respectively `,` and `"`;
- the `json` type you can configure the `jsonDepth` and the `jsonFlags` options just like when using the `json_decode` arguments, the default are the same;

Here's a example for casting a string via the `json` type.

```php
use League\Csv\Serializer;

#[Serializer\Cell(
    cast:Serializer\CastToArray::class,
    castArguments: [
        'type' => 'json',
        'jsonFlags' => JSON_BIGINT_AS_STRING
    ])
]
public array $data;
```

In the above example, the array has a JSON value associated with the key `data` and the `Serializer` will convert the
JSON string into an `array` and use the `JSON_BIGINT_AS_STRING` option of the `json_decode` function.

### Creating your own TypeCasting class

You can also provide your own class to typecast the array value according to your own rules. To do so, first,
specify your casting with the attribute:

```php
use League\Csv\Serializer;
#[Serializer\Cell(
    offset: 'rating',
    cast: IntegerRangeCasting::class,
    castArguments: ['min' => 0, 'max' => 5, 'default' => 2]
)]
private int $ratingScore;
```

The `IntegerRangeCasting` will convert cell value and return data between `0` and `5` and default to `2` if
the value is wrong or invalid. To allow your object to cast the cell value to your liking it needs to
implement the `TypeCasting` interface. To do so, you must define a `toVariable` method that will return
the correct value once converted. The first argument of the `__construct` method is always
the property type.

```php
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;

/**
 * @implements TypeCasting<int|null>
 */
readonly class IntegerRangeCasting implements TypeCasting
{
    public function __construct(
        string $propertyType,
        private int $min,
        private int $max,
        private int $default,
    ) {
        $this->isNullable = str_starts_with($type, '?');
    
        //the type casting class must only work with property declared as integer
        if ('int' !== ltrim($propertyType, '?')) {
            throw new TypeCastingFailed('The class '. self::class . ' can only work with integer typed property.');
        }
    
        if ($max < $min) {
            throw new LogicException('The maximum value can not be lesser than the minimum value.');
        }
    }

    public function toVariable(?string $value): ?int
    {
        // if the property is declared as nullable we exist early
        if (in_array($value, ['', null], true) && $this->isNullable) {
            return null;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min' => $this->min, 'max' => $this->max, 'default' => $this->default]]
        );
    }
}
```

As you have probably noticed, the class constructor arguments are given to the `Cell` attribute via the
`castArguments` which can provide more fine-grained behaviour.
