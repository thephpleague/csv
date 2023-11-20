---
layout: default
title: Denormalize a Tabular Data record into an object
---

# Record to object conversion

<p class="message-notice">New in version <code>9.12.0</code></p>

If you are working with a class which implements the `TabularDataReader` interface you can use this functionality
directly by calling the `TabularDataReader::getObjects` method.

Here's an example using the `Reader` class which implements the `TabularDataReader` interface:

```php
use League\Csv\Reader;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getObjects(ClimaticRecord::class) as $weather) {
    // each $weather entry will be an instance of the ClimaticRecord class;
}
```

In the following sections we will explain the mechanism use and how you can control it.

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
2011-01-01,,Abidjan
2011-01-02,24,Abidjan
2011-01-03,17,Abidjan
2011-01-01,18,Yamoussoukro
2011-01-02,23,Yamoussoukro
2011-01-03,21,Yamoussoukro
```

We can define a PHP DTO using the following properties.

```php
<?php

final class ClimaticRecord
{
    private ?DateTimeImmutable $date = null,

    public function __construct(
        public readonly Place $place,
        public readonly ?float $temperature,
    ) {
    }

    public function setDate(string $date): void
    {
        $this->date = new DateTimeImmutable($date, new DateTimeZone('Africa/Abidjan'));
    }
    
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }
}

enum Place
{
    case Yamoussoukro;
    case Abidjan;
}
```

To get instances of your object, you now can call `TabularData::getObjects` which returns
an `Iterator` containing only instances of your specified class.

```php
use League\Csv\Reader;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getObjects(ClimaticRecord::class) as $instance) {
    // each $instance entry will be an instance of the ClimaticRecord class;
}
```

## Defining the mapping rules

By default, the denormalization engine will automatically convert public properties using their name.
In other words, if there is:

- a public class property, which name is the same as a record key, the record value will be assigned to that property.
- a public class method, whose name starts with `set` and ends with the record key with the first character upper-cased, the record value will be assigned to the method first argument.

While the record value **MUST BE** a `string` or `null`, the autodiscovery feature only works with public properties typed with one of the following type:

- a scalar type (`string`, `int`, `float`, `bool`)
- `null`
- any `Enum` (backed or not)
- `DateTimeInterface` implementing class.
- an `array`

the `nullable` aspect of the property is also automatically handled.

To complete the conversion you can use the `Cell` attribute.

Here's an example of how the attribute can be used:

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

The above rule can be translated in plain English like this:

> Convert the value of the associative array whose key is `date` into a `CarbonImmutable` object
> using the date format `!Y-m-d` and the `Africa/Nairobi` timezone. Once created,
> inject the instance into the class private property `observedOn`.

This attribute will override the automatic resolution and enable fine-tuning type casting.
It can be used on class properties and methods regardless of their visibility.

The attribute can take up to three (3) arguments which are all optional:

- The `offset` argument tells the engine which record key to use via its numeric or name offset. If not present the property name or the name of the first argument of the `setter` method will be used. In such case, you are required to specify the property names information.
- The `cast` argument which accept the name of a class implementing the `TypeCasting` interface and responsible for type casting the record value. If not present, the mechanism will try to resolve the typecasting based on the propery or method argument type.
- The `castArguments` argument enables controlling typecasting by providing extra arguments to the `TypeCasting` class constructor. The argument expects an associative array and relies on named arguments to inject its value to the `TypeCasting` implementing class constructor.

<p class="message-notice">You can use the mechanism on a CSV without a header row but it requires
adding a <code>Cell</code> attribute on each property or method needed for the conversion.</p>

<p class="message-warning">The <code>reflectionProperty</code> key can not be used with the
<code>castArguments</code> as it is a reserved argument used by the <code>TypeCasting</code> class.</p>

In any case, if type casting fails, an exception will be thrown.

### Handling the empty string

Out of the box the mechanism makes no distinction between an empty string and the `null` value.
You can however change this behaviour using two (2) static methods:

- `League\Csv\Serializer\Denormalizer::allowEmptyStringAsNull`
- `League\Csv\Serializer\Denormalizer::disallowEmptyStringAsNull`

When called these methods will change the behaviour when it comes to handling empty string.
`Denormalizer::allowEmptyStringAsNull` will convert any empty string into the `null` value
before typecasting whereas `Denormalizer::disallowEmptyStringAsNull` will preserve the value.
Using these methods will affect the results of the process throughout your codebase.

```php
use League\Csv\Reader;
use League\Csv\Serializer\Denormalizer;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getObjects(ClimaticRecord::class) {
    // the first record contains an empty string for temperature
    // it is converted into the null value and handle by the
    // default conversion type casting;
}

Denormalizer::disallowEmptyStringAsNull();

foreach ($csv->getObjects(ClimaticRecord::class) {   
    // a TypeCastingFailed exception is thrown because we
    // can not convert the empty string into a valid
    // temperature property value
    // which expects `null` or a non-empty string.
}
```

## Type casting

The library comes bundled with seven (7) type casting classes which relies on the property type information.
All the built-in methods support the `nullable` and the `mixed` types.

- They will return `null` or a specified default value, if the cell value is `null` and the type is `nullable`
- If the value can not be cast they will throw an exception.

For scalar conversion, type casting is done via PHP's `ext-filter` extension.

### CastToString

Converts the array value to a string or `null` depending on the property type information. The class takes one
optional argument `default` which is the default value to return if the value is `null`.

<p class="notice">By default, this class is also responsible for automatically typecasting <code>mixed</code> typed properties.</p>

### CastToBool

Converts the array value to `true`, `false` or `null` depending on the property type information. The class takes one
optional argument `default` which is the default boolean value to return if the value is `null`.
Since typecasting relies on `ext-filter` rules, the following strings `1`, `true`, `on` and `yes` will all be cast
in a case-insensitive way to `true` otherwise `false` will be used.

<p class="notice">This class is also responsible for automatically typecasting <code>true</code> and <code>false</code> typed properties.</p>

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
use League\Csv\Serializer\Cell;

#[Cell(
    offset:1,
    cast:Serializer\CastToEnum::class,
    castArguments: ['default' => 'Abidjan', 'enum' => Place::class]
)]
public function setPlace(mixed $place): void
{
    //apply the method logic whatever that is!
}
```

> convert the value of the array whose offset is `1` into a `Place` Enum
> if the value is  null resolve the string `Abidjan` to `Place::Abidjan`. Once created,
> call the method `setPlace` with the created `Place` enum filling the `$place` argument.

<p class="notice">Using this class  with a <code>mixed</code> type without providing the <code>enum</code> parameter will trigger an exception.</p>

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify:

- the date format via the `format` argument
- the date timezone if needed  via the `timezone` argument
- the `default` which is the default value to return if the value is `null`; should be `null` or a parsable date time `string`
- the `dateClass` the class to use if the property is typed `mixed`.

If the property is typed with `mixed` or the `DateTimeInterface`, a `DateTimeImmutable` instance will be used if the `dateClass`
argument is not given. If given and invalid, an exception will be thrown.

### CastToArray

Converts the value into a PHP `array`. You are required to specify the array shape for the conversion to happen. The class
provides three (3) shapes:

- `list` converts the string using PHP `explode` function by default the separator called `delimiter` is `,`;
- `csv` converts the string using PHP `str_fgetcsv` function with its default options, the escape character is not available as its usage is not recommended to improve interoperability;
- `json` converts the string using PHP `json_decode` function with its default options;

The following are example for each shape expected string value:

```php
$array['list'] = "1,2,3,4";         //the string contains only a delimiter (shape list)
$arrat['csv'] = '"1","2","3","4"';  //the string contains delimiter and enclosure (shape csv)
$arrat['json'] = '{"foo":"bar"}';   //the string is a json string (shape json)
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
use League\Csv\Serializer;

#[Serializer\Cell(
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

Two mechanisms to extend typecasting are provided. You can register a closure via the `Denormalizer` class
or create a fully fledge `TypeCasting` class. Of course, the choice will depend on your use case.

### Registering a closure

You can register a closure using the `Denormalizer` class to convert a specific type. The type can be
any built-in type or a specific class.

```php
use App\Domain\Money\Naira;
use League\Csv\Serializer;

$castToNaira = function (?string $value, bool $isNullable, int $default = null): ?Naira {
    if (null === $value && $isNullable) {
        if (null !== $default) {
            return Naira::fromKobos($default);
        }

        return null;
    }

    return Naira::fromKobos(filter_var($value, FILTER_VALIDATE_INT));
};

Serializer\Denormalizer::registerType(Naira::class, $castToNaira);
```

The `Denormalizer` will automatically call the closure for any `App\Domain\Money\Naira` conversion. You can
also use the `Cell` attribute to further control the conversion

To do so specify your casting with the attribute:

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\Cell(offset: 'amount', castArguments: ['default' => 1000_00])]
private ?Naira $amount;
```

<p class="message-notice">No need to specify the <code>cast</code> argument as the closure is registered.</p>

In the following example, we redefine how to typecast to integer.

```php
use League\Csv\Serializer;

Serializer\Denormalizer::registerType('int', fn (?string $value): int => 42);
```

The closure will take precedence over the `CastToInt` class to convert
to the `int` type during autodiscovery. You can still use the `CastToInt`
class, but you are now require to explicitly declare it via the `Cell`
attribute using the `cast` argument.

The closure signature is the following:

```php
closure(?string $value, bool $isNullable, ...$arguments): mixed;
```

where:

- the `$value` is the record value
- the `$isNullable` tells whether the argument or property can be nullable
- the `$arguments` are the extra configuration options you can pass to the `Cell` attribute via `castArguments`

To complete the feature you can use `Denormalizer::unregisterType` to remove a registered closure for a specific `type`.

```php
use League\Csv\Serializer;

Serializer\Denormalizer::unregisterType(Naira::class);
```

The two (2) methods are static.

<p class="message-notice">the mechanism does not support <code>IntersectionType</code></p>

### Implementing a TypeCasting class

If you need to support `Intersection` type, or you want to be able to fine tune the typecasting
you can provide your own class to typecast the value according to your own rules. Since the class
is not registered by default you must configure its usage via the `Cell` attribute `cast` argument.

```php
use App\Domain\Money\Naira;
use League\Csv\Serializer;

#[Serializer\Cell(
    offset: 'amount',
    cast: App\Domain\Money\CastToNaira::class,
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
<?php

declare(strict_types=1);

namespace App\Domain\Money;

use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\TypeCasting;
use League\Csv\Serializer\TypeCastingFailed;

/**
 * @implements TypeCasting<Naira|null>
 */
final class CastToNaira implements TypeCasting
{
    private readonly bool $isNullable;
    private readonly ?Naira $default;

    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty, //always given by the Denormalizer
        ?int $default = null //can be filled via the Cell castArguments array destructuring
    ) {
        if (null !== $default) {
            $default = Naira::fromKobos($default);
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

    public function toVariable(?string $value): ?Naira
    {
        try {
            if (null === $value && $this->isNullable) {
                return $this->default;
            }

            return Naira::fromKobos(filter_var($value, FILTER_VALIDATE_INT));
        } catch (Throwable $exception) {
            throw new TypeCastingFailed('Unable to cast the given data `'.$value.'` to a `'.Money::class.'`.', 0, $exception);
        }
    }
}
```

<p class="message-info">While the built-in <code>TypeCasting</code> classes do not support Intersection Type, your own
implementing class can support them via inspection of the <code>$reflectionProperty</code> argument.</p>

## Using the feature without a TabularDataReader

The feature can be used outside the package usage via the `Denormalizer` class.

The class exposes four (4) methods to ease `array` to `object` conversion:

- `Denormalizer::denormalizeAll` and `Denormalizer::assignAll` to convert a collection of records into a collection of instances of a specified class.
- `Denormalizer::denormalize` and `Denormalizer::assign` to convert a single record into a new instance of the specified class.

Since we are not leveraging the `TabularDataReader` we must explicitly tell the class how to link array keys and class properties.
Once instantiated you can reuse the instance to independently convert a single record or a collection of `array`.

```php
use League\Csv\Serializer\Denormalizer;

$record = [
    'date' => '2023-10-30',
    'temperature' => '-1.5',
    'place' => 'Yamoussoukro',
];

//a complete collection of records as shown below
$collection = [$record];
//we first instantiate the denormalizer
//and we provide the information to map record key to the class properties
$denormalizer = new Denormalizer(ClimaticRecord::class, ['date', 'temperature', 'place']);
$weather = $denormalizer->denormalize($record); //we convert 1 record into 1 instance

foreach ($denormalizer->denormalizeAll($collection) as $weather) {
    // each $weather entry will be an instance of the ClimaticRecord class;
}
```

To complete the feature 2 static methods are provided if you only need to denormalization once,
`Denormalizer::assign` will automatically use the `array` keys as property names. Whereas,
you still need to give the property list to `Denormalizer::assignAll` to allow the class
to work with any given iterable structure of `array`.

```php
<?php
// you can use the alternate syntactic sugar methods 
// if you only need to use the mechanism once
$weather = Denormalizer::assign(ClimaticRecord::class, $record);

foreach (Denormalizer::assignAll(ClimaticRecord::class, $collection, ['date', 'temperature', 'place']) as $weather) {
    // each $weather entry will be an instance of the ClimaticRecord class;
}
```

Every rule and setting explain before applies to the `Denormalizer` usage.
