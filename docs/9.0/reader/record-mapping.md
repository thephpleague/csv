---
layout: default
title: Deserializing a Tabular Data record into an object
---

# Mapping records to objects

<p class="message-notice">New in version <code>9.12.0</code></p>

## Converting an array to an object

To work with objects instead of arrays the `Serializer` class is introduced to expose a deserialization mechanism.

The class exposes two (2) methods to ease `array` to `object` conversion in the context of tabular data:

- `Serializer::deserialize` which converts a single record into an instance of the specified class.
- `Serializer::deserializeAll` which converts a collection of records and returns a collection of the specified class instances.

```php
use League\Csv\Serializer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '-1.5',
    'place' => 'Berkeley',
];

$serializer = new Serializer(Weather::class, ['date', 'temperature', 'place']);
$weather = $serializer->deserialize($record);

$collection = [$record];
foreach ($serializer->deserializeAll($collection) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

If you are working with a class which implements the `TabularDataReader` interface you can use this functionality
directly by calling the `TabularDataReader::getObjects` method.

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
- information on how to convert cell value into object properties using dedicated attributes;

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

- a scalar type (`string`, `int`, `float`, `bool`)
- any `Enum` object (backed or not)
- `DateTime`, `DateTimeImmuntable` or any class that extends those two classes.
- an `array`

the `nullable` aspect of the property is also handled.

To fine tune the conversion you are require to use the `Cell` attribute. This attribute will
override the automatic resolution and enable fine-tuning type casting on the property level.

The `Cell` attribute can be used on class properties and methods regardless of their visibility.
The attribute can take up to three (3) arguments which are all optional:

- The `offset` argument tells the engine which cell to use via its numeric or name offset. If not present  
the property name or the name of the first argument of the `setter` method will be used. In such case,  
you are required to specify the property names information.
- The `cast` argument which accept the name of a class implementing the `TypeCasting` interface and responsible  
for type casting the cell value.
- The `castArguments` which enable controlling typecasting by providing extra arguments to the `TypeCasting` class constructor

In any cases, if type casting fails, an exception will be thrown.

Here's an example of how the attribute could be used:

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

> convert the value of the associative array whose key is `date` into a `CarbonImmutable` object
> using the date format `!Y-m-d` and the `Africa/Nairobi` timezone. Once created,
> inject the date instance into the `observedOn` property of the class.

## Type casting the record value

The library comes bundles with seven (7) type casting classes which relies on the property type information. All the
built-in methods support the `nullable` and the `mixed` types.

- They will return `null` or a specified default value, if the cell value is `null` and the type is `nullable`
- If the value can not be cast they will throw an exception.

All classes are defined under the `League\Csv\Serializer` namespace.

### CastToString

Converts the array value to a string or `null` depending on the property type information. The class takes on
optional argument `default` which is the default value to return if the value is `null`.

### CastToBool

Converts the array value to `true`, `false` or `null` depending on the property type information. The class takes on
optional argument `default` which is the default boolean value to return if the value is `null`.

### CastToInt and CastToFloat

Converts the array value to an `int` or a `float` depending on the property type information. The class takes three (3)
optional argument:

- `default` which is the default value to return if the value is `null`; respectively an `int`, a `float` or `null`.
- `min` the minimum accepted value
- `max` the maximum accepted value

### CastToEnum

Convert the array value to a PHP `Enum` it supported both "real" and backed enumeration.  The class takes on
optional argument `default` which is the default Enum value to return if the value is `null`.
If the `Enum` is backed the cell value will be considered as one of the Enum value; otherwise it will be used
as one the `Enum` name. Likewise, the `default` value will also be considered the same way. If the default value
is not `null` and the value given is incorrect, the mechanism will throw an exception.

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify:

- the date format via the `format` argument
- the date timezone if needed  via the `timezone` argument
- the `default` which is the default value to return if the value is `null`; should be a `null` or a parsable date time `string`

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
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\Cell(
    offset: 'amout',
    cast: CastToMoney::class,
    castArguments: ['min' => -10000_00, 'max' => 10000_00, 'default' => 100_00]
)]
private Money $ratingScore;
```

The `CastToAmount` will convert cell value and return data between `-100_00` and `100_00` and default to `20_00` if
the value is wrong or invalid. To allow your object to cast the cell value to your liking it needs to
implement the `TypeCasting` interface. To do so, you must define a `toVariable` method that will return
the correct value once converted. The first argument of the `__construct` method is always
the property type.

```php
use App\Domain\Money;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;

/**
 * @implements TypeCasting<Money|null>
 */
class CastToMoney implements TypeCasting
{
    public function __construct(
        string $propertyType, //always required and given by the Serializer implementation
        private readonly int $min,
        private readonly int $max,
        private readonly int $default,
    ) {
        $this->isNullable = str_starts_with($type, '?');
    
        //the type casting class must only work with property declared as integer
        if (Money::class !== ltrim($propertyType, '?')) {
            throw new TypeCastingFailed('The class '. self::class . ' can only work with `' . Money::class . '` typed property.');
        }
    
        if ($max < $min) {
            throw new LogicException('The maximum value can not be lesser than the minimum value.');
        }
    }

    public function toVariable(?string $value): ?Money
    {
        // if the property is declared as nullable we exist early
        if (in_array($value, ['', null], true) && $this->isNullable) {
            return null;
        }

        return Money::fromNaira(filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min' => $this->min, 'max' => $this->max, 'default' => $this->default]]
        ));
    }
}
```

As you have probably noticed, the class constructor arguments are given to the `Cell` attribute via the
`castArguments` which can provide more fine-grained behaviour.
