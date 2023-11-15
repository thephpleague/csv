---
layout: default
title: Denormalize a Tabular Data record into an object
---

# Record to object conversion

<p class="message-notice">New in version <code>9.12.0</code></p>

## Assign an array to an object

To work with objects instead of arrays the `Denormalizer` class is introduced to expose a
text based denormalization mechanism for tabular data.

The class exposes four (4) methods to ease `array` to `object` conversion:

- `Denormalizer::denormalizeAll` and `Denormalizer::assignAll` which convert a collection of records into a collection of instances of a specified class.
- `Denormalizer::denormalize` and `Denormalizer::assign` which convert a single record into a new instance of the specified class.

```php
use League\Csv\Denormalizer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '-1.5',
    'place' => 'Berkeley',
];

//a complete collection of records as shown below
$collection = [$record];
//we first instantiate the denormalizer
$denormalizer = new Denormalizer(Weather::class, ['date', 'temperature', 'place']);

$weather = $denormalizer->denormalize($record); //we convert 1 record into 1 instance
foreach ($denormalizer->denormalizeAll($collection) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}

// you can use the alternate syntactic sugar methods 
// if you only need the denormalizing mechanism once
$weather = Denormalizer::assign(Weather::class, $record);

foreach (Denormalizer::assignAll(Weather::class, $collection, ['date', 'temperature', 'place']) as $weather) {
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

## Prerequisite

The denormalization mechanism works mainly with DTO or objects
without complex logic in their constructors.

<p class="message-notice">The mechanism relies on PHP's <code>Reflection</code>
feature. It does not use the class constructor to perform the conversion.
This means that if the targeted object contains additional logic in its constructor,
the mechanism may either fail or produced unexpected results.</p>

To work as intended the mechanism expects the following:

- A target class where the array will be denormalized in;
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
        public ?float $temperature,
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

To get instances of your object, you now can call one of the `Denormalizer` method as show below:

```php
use League\Csv\Reader;
use League\Csv\Denormalizer

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
$denormalizer = new Denormalizer(Weather::class, $csv->header());

foreach ($csv as $record) {
   $weather = $denormalizer->denormalize($record);
}

//or

foreach ($denormalizer->denormalizeAll($csv) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}

//or 

foreach (Denormalizer::assignAll(Weather::class, $csv, $csv->getHeader()) as $weather) {
    // each $weather entry will be an instance of the Weather class;
}
```

<p class="notice">The code above is similar to using <code>TabularDataReader::getObjects</code> method.</p>

## Defining the mapping rules

By default, the denormalization engine will automatically convert public properties using their name.
In other words, if there is a public class property, which name is the same as a record key,
the record value will be assigned to that property. The record value **MUST BE** a
`string` or `null` and the object public properties **MUST BE** typed with one of
the following type:

- a scalar type (`string`, `int`, `float`, `bool`)
- any `Enum` object (backed or not)
- `DateTimeInterface` implementing class.
- an `array`

the `nullable` aspect of the property is also automatically handled.

To complete the conversion you can use the `Cell` attribute. This attribute will override
the automatic resolution and enable fine-tuning type casting. It can be used on class
properties and methods regardless of their visibility.

The attribute can take up to three (3) arguments which are all optional:

- The `offset` argument tells the engine which record key to use via its numeric or name offset. If not present the property name or the name of the first argument of the `setter` method will be used. In such case, you are required to specify the property names information.
- The `cast` argument which accept the name of a class implementing the `TypeCasting` interface and responsible for type casting the record value. If not present, the mechanism will try to resolve the typecasting based on the propery or method argument type.
- The `castArguments` argument enables controlling typecasting by providing extra arguments to the `TypeCasting` class constructor. The argument expects an associative array and relies on named arguments to inject its value to the `TypeCasting` implementing class constructor.

<p class="message-warning">The <code>reflectionProperty</code> key can not be used with the
<code>castArguments</code> as it is a reserved argument used by the <code>TypeCasting</code> class.</p>

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

> Convert the value of the associative array whose key is `date` into a `CarbonImmutable` object
> using the date format `!Y-m-d` and the `Africa/Nairobi` timezone. Once created,
> inject the instance into the class private property `observedOn`.

### Handling the empty string

Out of the box the `Denormalizer` makes no distinction between an empty string and the `null` value.
You can however change this behaviour using two (2) static methods:

- `Denormalizer::allowEmptyStringAsNull`
- `Denormalizer::disallowEmptyStringAsNull`

When called these methods will change the class behaviour when it comes to handling empty string.
`Denormalizer::allowEmptyStringAsNull` will trigger conversion of all empty string into the `null` value
before typecasting whereas `Denormalizer::disallowEmptyStringAsNull` will maintain the distinction.
Using these methods will affect the `Denormalizer` usage throughout your codebase.

```php
use League\Csv\Denormalizer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '',
    'place' => 'Berkeley',
];

$weather = Denormalizer::assign(Weather::class, $record);
$weather->temperature; // returns null

Denormalizer::disallowEmptyStringAsNull();
Denormalizer::assign(Weather::class, $record);
//a TypeCastingFailed exception is thrown because we
//can not convert the empty string into a temperature property
//which expects `null` or a non-empty string.
```

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

<p class="notice">By default, this class is also responsible for automatically typecasting <code>mixed</code> typed properties.</p>

### CastToBool

Converts the array value to `true`, `false` or `null` depending on the property type information. The class takes one
optional argument `default` which is the default boolean value to return if the value is `null`.
Since typecasting relies on `ext-filter` rules, the following strings `1`, `true`, `on` and `yes` will all be cast
in a case-insensitive way to `true` otherwise `false` will be used.

### CastToInt and CastToFloat

Converts the array value to an `int` or a `float` depending on the property type information. The class takes one
optional argument `default` which is the default `int` or `float` value to return if the value is `null`.

### CastToEnum

Convert the array value to a PHP `Enum`, it supports both "real" and backed enumeration. The class takes two (2)
optionals arguments:

- `default` which is the default Enum value to return if the value is `null`.
- `enum` which is the Enum class to use for resolution if the property or method argument is typed as `mixed`.

If the `Enum` is backed the cell value will be considered as one of the Enum value; otherwise it will be used
as one the `Enum` name. The same logic applies for the `default` value. If the default value
is not `null` and the value given is incorrect, the mechanism will throw an exception.

```php
use League\Csv\Serializer;

#[Serializer\Cell(
    offset:1,
    cast:Serializer\CastToEnum::class,
    castArguments: ['default' => 'Galway', 'enum' => Place::class]
)]
public function setPlace(mixed $place): void
{
    //apply the method logic whatever that is!
}
```

> convert the value of the array whose offset is `1` into a `Place` Enum
> if the value is  null resolve the string `Galway` to `Place::Galway`. Once created,
> call the method `setPlace` with the created `Place` enum filling the `$place` argument.

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify:

- the date format via the `format` argument
- the date timezone if needed  via the `timezone` argument
- the `default` which is the default value to return if the value is `null`; should be `null` or a parsable date time `string`

If the property is typed with `mixed` or the `DateTimeInterface` a `DateTimeImmutable` instance will be used.

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

In the above example, the array has a JSON value associated with the key `data` and the `Denormalizer` will convert the
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

## Extending Type Casting capabilities

We provide two mechanisms to extend typecasting. You can register a closure via the `Denormalizer` class
or create a fully fledge `TypeCasting` class. Of course, the choice will depend on your use case.

### Registering a closure

You can register a closure using the `Denormalizer` class to convert a specific type. The type can be
any built-in type or a specific class.

```php
use App\Domain\Money;
use League\Csv\Denormalizer;

$typeCasting = function (
    ?string $value,
    bool $isNullable,
    ?int $default = 20_00
 ): ?Money {
    if (null === $value && $isNullable) {
        if (null !== $default) {
            return Money::fromNaira($default);
        }

        return null;
    }

    return Money::fromNaira(filter_var($value, FILTER_VALIDATE_INT));
}

Denormalizer::registerType(Money::class, $typeCasting);
```

The `Denormalizer` will automatically call the closure for any `App\Domain\Money` conversion. You can
also use the `Cell` attribute to further control the conversion

To do so, first, specify your casting with the attribute:

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\Cell(offset: 'amount', castArguments: ['default' => 20_00])]
private ?Money $naira;
```

<p class="message-notice">No need to specify the <code>cast</code> argument as the closure is registered.</p>

In the following example, the closure takes precedence over the `CastToInt` class to convert
to the `int` type. If you still wish to use the `CastToInt` class you are require to
explicitly declare it via the `Cell` attribute `cast` argument.

```php
use League\Csv\Denormalizer;

Denormalizer::registerType('int', fn (?string $value): int => 42);
```

The closure signature is the following:

```php
closure(?string $value, bool $isNullable, ...$arguments): mixed;
```

where:

- the `$value` is the record value
- the `$isNullable` tells whether the argument or property can be nullable
- the `$arguments` are the extra configuration options you can pass to the `Cell` attribute via `castArguments`

To complete the feature you can use:

- `Denormalizer::unregisterType` to remove the registered closure for a specific `type`;

The two (2) methods are static.

<p class="message-notice">the mechanism does not support <code>IntersectionType</code></p>

### Implementing a TypeCasting class

If you need to support `Intersection` type, or you want to be able to fine tune the typecasting
you can provide your own class to typecast the value according to your own rules. Since the class
is not registered by default you must configure its usage via the `Cell` attribute `cast` argument.

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\Cell(
    offset: 'amount',
    cast: App\Domain\CastToNaira::class,
    castArguments: ['default' => 20_00]
)]
private ?Money $naira;
```

The `CastToMoney` will convert the cell value into a `Money` object and if the value is `null`, `20_00` will be used.
To allow your object to cast the cell value to your liking it needs to implement the `TypeCasting` interface.
To do so, you must define a `toVariable` method that will return the correct value once converted.

<p class="message-warning"><strong>Of note</strong> The class constructor method must take the property type value as
one of its argument with the name <code>$reflectionProperty</code>. This means you <strong>can not</strong> use the
<code>reflectionProperty</code> as a possible key of the associative array given to <code>castArguments</code></p>

```php
use App\Domain\Money;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;

/**
 * @implements TypeCasting<Money|null>
 */
final class CastToNaira implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly Money $default;

    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty, //always given by the Serializer
        ?int $default = null //can be filled via the Cell castArguments array destructuring
    ) {
        if (null !== $default) {
            $default = Money::fromNaira($default);
        }
        $this->default = $default;

        // It is recommended to handle the $reflectionProperty argument.
        // The argument gives you access to property/argument information.
        // it allows validating that the argument does support your casting
        // it allows adding support to union, intersection or unnamed type 
        // it tells whether the property/argument is nullable or not

        $reflectionType = $reflectionProperty->getType();
        if (!$reflectionType instanceof ReflectionNamedType || !in_array($reflectionType->getName(), [Money::class, 'mixed'], true)) {
            throw new MappingFailed(match (true) {
                $reflectionProperty instanceof ReflectionParameter => 'The setter method argument `'.$reflectionProperty->getName().'` is not typed with the '.Money::class.' class or with `mixed`.',
                $reflectionProperty instanceof ReflectionProperty => 'The property `'.$reflectionProperty->getName().'` is not typed with the '.Money::class.' class or with `mixed`.',
            });
        }
        $this->isNullable = $reflectionType->allowsNull();
    }

    public function toVariable(?string $value): ?Money
    {
        try {
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

<p class="message-info">While the built-in <code>TypeCasting</code> classes do not support Intersection Type, your own
implementing class can support them via inspection of the <code>$reflectionProperty</code> argument.</p>
