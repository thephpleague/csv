---
layout: default
title: Deserializing a Tabular Data record into an object
---

# Mapping records to objects

<p class="message-notice">New in version <code>9.12.0</code></p>

## Converting an array to an object

To work with objects instead of arrays the `Serializer` class is introduced to expose a
text based deserialization mechanism for tabular data.

The class exposes three (3) methods to ease `array` to `object` conversion:

- `Serializer::deserializeAll` which converts a collection of records into a collection of instances of a specified class.
- `Serializer::deserialize` which converts a single record into a new instance of the specified class.
- `Serializer::assign` which is a syntactic sugar static method to use in place of `Serializer::deserialize`.

```php
use League\Csv\Serializer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '-1.5',
    'place' => 'Berkeley',
];

$serializer = new Serializer(Weather::class, ['date', 'temperature', 'place']);
$weather = $serializer->deserialize($record);

// you can use the syntactic sugar method as an alternative
// if you only need to do it once
$weather = Serializer::assign(Weather::class, $record);

//once the serializer instantiated you can also deserialize
//a complete collection of records as shown below
$collection = [$record];
foreach ($serializer->deserializeAll($collection) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

If you are working with a class which implements the `TabularDataReader` interface you can use this functionality
directly by calling the `TabularDataReader::getObjects` method.

Here's an example using the `Reader` class which implements the `TabularDataReader` interface:

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

The deserialization mechanism works mainly with DTO or objects
without complex logic in their constructors.

<p class="message-notice">The mechanism relies on PHP's <code>Reflection</code>
feature. It does not use the class constructor to perform the conversion.
This means that if the targeted object contains additional logic in its constructor,
the mechanism may either fail or produced unexpected results.</p>

To work as intended the mechanism expects the following:

- A target class where the array will be deserialized in;
- information on how to convert cell values into object properties;

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

We can define a PHP DTO using the following properties.

```php
<?php

use League\Csv\Serializer\Cell;

final readonly class Weather
{
    public function __construct(
        public float $temperature,
        public Place $place,
        public DateTimeImmutable $date,
    ) {
    }
}

enum Place
{
    case Berkeley;
    case Galway;
}
```

To get instances of your object, you now can call one of the `Serializer` method as show below:

```php
use League\Csv\Reader;
use League\Csv\Serializer

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
$serializer = new Serializer(Weather::class, $csv->header());

foreach ($csv as $record) {
   $weather = $serializer->deserialize($record);
}

//or

foreach ($serializer->deserializeAll($csv) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

<p class="notice">The code above is similar to using <code>TabularDataReader::getObjects</code> method.</p>

## Defining the mapping rules

By default, the deserialization engine will automatically convert public properties using their name.
In other words, if there is a public class property, which name is the same as a record key,
the record value will be assigned to that property. The record value **MUST BE** a
`string` or `null` and the object public properties **MUST BE** typed with one of
the following type:

- a scalar type (`string`, `int`, `float`, `bool`)
- any `Enum` object (backed or not)
- `DateTimeInterface` implementing class.
- an `array`

the `nullable` aspect of the property is also automatically handled.

To improve the conversion you can use the `Cell` attribute. This attribute will override
the automatic resolution and enable fine-tuning type casting on the property level.

The `Cell` attribute can be used on class properties and methods regardless of their visibility.
The attribute can take up to three (3) arguments which are all optional:

- The `offset` argument tells the engine which record key to use via its numeric or name offset. If not present the property name or the name of the first argument of the `setter` method will be used. In such case, you are required to specify the property names information.
- The `cast` argument which accept the name of a class implementing the `TypeCasting` interface and responsible for type casting the record value.
- The `castArguments` argument enables controlling typecasting by providing extra arguments to the `TypeCasting` class constructor. The argument expects an associative array and relies on named arguments to inject its value to the `TypeCasting` implementing class constructor.

<p class="message-warning">The <code>propertyType</code> key can not be used with the <code>castArguments</code> as it is reserved argument used by the <code>TypeCasting</code> class.</p>

In any case, if type casting fails, an exception will be thrown.

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
private CarbonImmutable $observedOn;
```

The above rule can be translated in plain english like this:

> convert the value of the associative array whose key is `date` into a `CarbonImmutable` object
> using the date format `!Y-m-d` and the `Africa/Nairobi` timezone. Once created,
> inject the date instance into the class private property `observedOn`.

## Type casting

The library comes bundled with seven (7) type casting classes which relies on the property type information.
All the built-in methods support the `nullable` and the `mixed` types.

- They will return `null` or a specified default value, if the cell value is `null` and the type is `nullable`
- If the value can not be cast they will throw an exception.

For scalar conversion, type casting is done via PHP's `ext-filter` extension.

All classes are defined under the `League\Csv\Serializer` namespace.

### CastToString

Converts the array value to a string or `null` depending on the property type information. The class takes one
optional argument `default` which is the default value to return if the value is `null`.

<p class="notice">By default, this class is also responsible for automatically typecasting `mixed` properties.</p>

### CastToBool

Converts the array value to `true`, `false` or `null` depending on the property type information. The class takes one
optional argument `default` which is the default boolean value to return if the value is `null`.

### CastToInt and CastToFloat

Converts the array value to an `int` or a `float` depending on the property type information. The class takes one
optional argument `default` which is the default `int` or `float` value to return if the value is `null`.

### CastToEnum

Convert the array value to a PHP `Enum`, it supports both "real" and backed enumeration. The class takes one
optional argument `default` which is the default Enum value to return if the value is `null`.
If the `Enum` is backed the cell value will be considered as one of the Enum value; otherwise it will be used
as one the `Enum` name. Likewise, the `default` value will also be considered the same way. If the default value
is not `null` and the value given is incorrect, the mechanism will throw an exception.

```php
use League\Csv\Serializer;

#[Serializer\Cell(
    offset:1,
    cast:Serializer\CastToEnum::class,
    castArguments: ['default' => 'Galway']
)]
public function setPlace(Place $place): void
{
    //apply the method logic whatever that is!
}
```

> convert the value of the array whose offset is `1` into a `Place` Enum
> if the value is  null resolve the string `Kidal` to `Place::Galway`. Once created,
> call the method `setPlace` with the created `Place` enum.

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify:

- the date format via the `format` argument
- the date timezone if needed  via the `timezone` argument
- the `default` which is the default value to return if the value is `null`; should be `null` or a parsable date time `string`

### CastToArray

Converts the value into a PHP `array`. You are required to specify the array shape for the conversion to happen. The class
provides three (3) shapes:

- `list` converts the string using PHP `explode` function by default the separator called `delimiter` is `,`;
- `csv` converts the string using PHP `str_fgetcsv` function with its default options, the escape character is not available as its usage is not recommended to improve interoperability;
- `json` converts the string using PHP `json_decode` function with its default options;

The following are example for each shape expected string value:

```php
$array['list'] = "1,2,3,4";         //the string contains only a delimiter (type list)
$arrat['csv'] = '"1","2","3","4"';  //the string contains delimiter and enclosure (type csv)
$arrat['json'] = '{"foo":"bar"}';   //the string is a json string (type json)
```

Here's an example for casting a string via the `json` shape.

```php
use League\Csv\Serializer;

#[Serializer\Cell(
    cast:Serializer\CastToArray::class,
    castArguments: [
        'shape' => 'json',
        'jsonFlags' => JSON_BIGINT_AS_STRING
    ])
]
private array $data;
```

In the above example, the array has a JSON value associated with the key `data` and the `Serializer` will convert the
JSON string into an `array` and use the `JSON_BIGINT_AS_STRING` option of the `json_decode` function.

If you use the array shape `list` or `csv` you can also typecast the `array` content using the
optional `type` argument as shown below.

```php
use League\Csv\Serializer\Cell;

#[Cell(
    cast:Serializer\CastToArray::class,
    castArguments: [
        'shape' => 'csv',
        'delimiter' => ';',
        'type' => 'float',
    ])
]
public function setData(array $data): void;
```

If the conversion succeeds, then the property will be set with an `array` of `float` values.
The `type` option only supports scalar type (`string`, `int`, `float` and `bool`)

### Creating your own TypeCasting class

You can also provide your own class to typecast the array value according to your own rules. To do so, first,
specify your casting with the attribute:

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\Cell(
    offset: 'amount',
    cast: App\Domain\CastToMoney::class,
    castArguments: ['default' => 100_00]
)]
private Money $naira;
```

The `CastToMoney` will convert the cell value into a `Money` object and if the value is `null`, `20_00` will be used.
To allow your object to cast the cell value to your liking it needs to implement the `TypeCasting` interface.
To do so, you must define a `toVariable` method that will return the correct value once converted.
**Of note**, The first argument of the `__construct` method is always the property type.

```php
use App\Domain\Money;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;

/**
 * @implements TypeCasting<Money|null>
 */
final class CastToMoney implements TypeCasting
{
    private readonly ?Money $default;

    public function __construct(
        string $propertyType, //always required and given by the Serializer implementation
        int $default = null,
    ) {
        $this->isNullable = str_starts_with($type, '?');
    
        //the type casting class must only work with the declared type
        //Here the TypeCasting object only cares about converting
        //data into a Money instance.
        if (Money::class !== ltrim($propertyType, '?')) {
            throw new MappingFailed('The class '. self::class . ' can only work with `' . Money::class . '` typed property.');
        }

        if (null !== $default) {
            try {
                $this->default = $this->toVariable($default);
            } catch (TypeCastingFailed $exception) {
                throw new MappingFailed('Unable to cast the default value `'.$value.'` to a `'.Money::class.'`.', 0, $exception);
            }
        }
    }

    public function toVariable(?string $value): ?Money
    {
        try {
            // if the property is declared as nullable we exist early
            if (null === $value && $this->isNullable) {
                return $this->default;
            }
    
            return Money::fromNaira(filter_var($value, FILTER_VALIDATE_INT));
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a `'.Money::class.'`.', 0, $exception);
        }
    }
}
```
