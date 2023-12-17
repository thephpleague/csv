---
layout: default
title: Denormalize a Tabular Data record into an object
---

# Record to object conversion

<p class="message-notice">New in version <code>9.12.0</code></p>

If you are working with a class which implements the `TabularDataReader` interface you can now deserialize
your data using the `TabularDataReader::getObjects` method. The method will convert your document records
into objects using PHP's powerful Reflection API.

Here's an example using the `Reader` class which implements the `TabularDataReader` interface:

```php
use League\Csv\Reader;

$csv = Reader::createFromString($document);
$csv->setHeaderOffset(0);
foreach ($csv->getObjects(ClimaticRecord::class) as $weather) {
    // each $weather entry will be an instance of the ClimaticRecord class;
}
```

In the following sections we will explain the process and how you can control it.

<p class="message-info">Of note, specifying the header offset is not mandatory for the mechanism to work.</p>

## Prerequisite

The deserialization process is done in two steps. The first step is decoding your CSV into
a collection of records. This part is already handle by the package. Once decoding is done,
the denormalization mechanism can happen. The process is geared toward converting records
into DTOs or objects without complex logic in their constructors.

<p class="message-notice">The mechanism relies on PHP's <code>Reflection</code>
feature. It does not use the class constructor to perform the conversion.
This means that if the targeted object contains additional logic in its constructor,
the mechanism may either fail or produced unexpected results.</p>

To work as intended the mechanism expects the following:

- A target class where the array will be denormalized in;
- information on how to convert cell values into object properties;

As an example throughout the documentation we will assume the following CSV document:

```csv
date,temperature,place
2011-01-01,,Abidjan
2011-01-02,24,Abidjan
2011-01-03,17,Abidjan
2011-01-01,18,Yamoussoukro
2011-01-02,23,Yamoussoukro
2011-01-03,21,Yamoussoukro
```

and define a PHP DTO using the following properties.

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

To get instances of your object, you now can call `TabularDataReader::getObjects` which returns
an `Iterator` containing only instances of your specified class.

```php
use League\Csv\Reader;

$csv = Reader::createFromString($document);
foreach ($csv->getObjects(ClimaticRecord::class) as $instance) {
    // each $instance entry will be an instance of the ClimaticRecord class;
}
```

## Defining the mapping rules

By default, the denormalization engine will automatically fill public properties and call public methods
using their names. In other words, if there is:

- a public class property, which name is the same as a record key, the record value will be assigned to that property.
- a public class method, whose name starts with `set` and ends with the record key with the first character upper-cased, the record value will be assigned to the method first argument.

While the record value **MUST BE** a `string` or `null`, the autodiscovery feature works out of the box with
public properties or arguments typed with one of the following type:

- a scalar type (`string`, `int`, `float`, `bool`)
- `null`
- any `Enum` (backed or not)
- `DateTimeInterface` implementing class.
- an `array`

the `nullable` aspect of the property is also automatically handled.

If the autodiscovery feature is not enough, you can complete the conversion information using the following
PHP attributes:

- the `League\Csv\Serializer\MapCell`
- the `League\Csv\Serializer\AfterMapping`

<p class="message-info">The <code>AfterMapping</code> attribute is added in version <code>9.13.0</code></p>

### Improving record mapping

Here's an example of how the `League\Csv\Serializer\MapCell` attribute works:

```php
use League\Csv\Serializer;
use Carbon\CarbonImmutable;

#[Serializer\MapCell(
    column: 'date',
    cast: Serializer\CastToDate::class,
    options: [
        'format' => '!Y-m-d',
        'timezone' => 'Africa/Nairobi'
    ])
]
private CarbonImmutable $observedOn;
```

The above rule can be translated in plain English like this:

> Convert the value of the associative array whose key is `date` into a `CarbonImmutable` object
> using the `CastToDate` class qith the date format `!Y-m-d` and the `Africa/Nairobi` timezone.
> Once created, inject the instance into the class private property `observedOn`.

This attribute will override any automatic resolution and enable fine-tuning type casting.
It can be used on class properties and methods regardless of their visibility and their type.

The attribute can take up to four (4) arguments which are all optional:

- The `column` a string or an integer that tells the engine which record key to use via its offset or key. If not present the property name or the name of the first argument of the `setter` method will be used. In such case, you are required to specify the property names information.
- The `cast` a string which represents the name of a class implementing the `TypeCasting` interface or an alias and responsible for type casting the record value. If not present, the mechanism will try to resolve the typecasting based on the property or method argument type.
- The `options` an associative array to improve typecasting by providing extra options per class/alias. The argument expects an associative array and relies on named arguments to inject its value to the method.
- The `ignore` a boolean which control if the property or method should be completely ignored by the mechanism. By default, its value is `false`. This property takes precedence over all the other properties of the attribute once set to `true`.

<p class="message-info">The <code>ignore</code> argument was added in version <code>9.13.0</code></p>
<p class="message-info">You can use the mechanism on a CSV without a header row but it requires
adding a <code>MapCell</code> attribute on each property or method needed for the conversion. Or you
can use the optional second argument of <code>TabularDataReader::getObjects</code> to specify the
header value, just like with <code>TabularDataReader::getRecords</code></p>

In any case, if type casting fails, an exception will be thrown.

### Improving object creation

<p class="message-info">The feature is available since version <code>9.13.0</code></p>

Because we are not using the object constructor method, we need a way to work around that limitation
and tagging one or more methods that should be called after all mapping is done to return a valid object.
Tagging is made using the `League\Csv\Serializer\AfterMapping` attribute.

```php
use League\Csv\Serializer;

#[Serializer\AfterMapping('validate')]
final class ClimateRecord
{
    public function __construct(
        public readonly Place $place,
        public readonly ?float $temperature,
        public readonly ?DateTimeImmutable $date,
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        //further validation on your object
        //or any other post construction methods
        //that is needed to be called
    }
}
```

In the above example, the `validate` method will be call once all the properties have been set but
before the object is returned. You can specify as many methods belonging to the class as you want
regardless of their visibility by separating them with a comma. The methods will be called
in the order they have been declared.

<p class="message-notice">If the method does not exist or requires explicit arguments an exception will be thrown.</p>

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
They all support `nullable`, `mixed` as well as non-typed properties.

- They will return `null` or a specified default value, if the cell value is `null` and the type is `nullable`
- If the value can not be cast they will throw an exception.

For scalar conversion, type casting is done via PHP's `ext-filter` extension.

<p class="message-info">Untyped properties are considered as being <code>mixed</code> type.</p>

They are all registered by default so you do not need to specify them using the `cast` property of the `MapCell`
attribute **unless you are using them on untyped or mixed property where using `cast` is mandatory**

### CastToString

Converts the array value to a string or `null` depending on the property type information.
The class takes one optional argument `default` which is the default value to return if
the value is `null`.

```php
use League\Csv\Serializer\MapCell;

#[MapCell(options: ['default' => 'Kouyaté'])]
private ?string $firstname;
```

<p class="message-info">By default, this class is also responsible for automatically typecasting <code>mixed</code> typed properties.</p>
<p class="message-info">Since the class is used by default you do not need to specify it via the <code>cast</code> property.</p>

### CastToBool

Converts the array value to `true`, `false` or `null` depending on the property type information.
The class takes one optional argument `default` which is the default boolean value to return if
the value is `null`.

<p cLass="message-info">Since typecasting relies on <code>ext-filter</code> rules, the following strings
<code>1</code>, <code>true</code>, <code>on</code> and <code>yes</code> will all be cast in a
case-insensitive way to <code>true</code> otherwise <code>false</code> will be used.</p>

```php
use League\Csv\Serializer\MapCell;

#[MapCell(options: ['default' => false])]
private ?bool $isValid;
```

<p class="message-notice">This class is also responsible for automatically typecasting <code>true</code> and <code>false</code> typed properties.</p>

### CastToInt and CastToFloat

Converts the array value to an `int` or a `float` depending on the property type information. The class takes one
optional argument `default` which is the default `int` or `float` value to return if the value is `null`.

```php
use League\Csv\Serializer\CastToInt;
use League\Csv\Serializer\MapCell;

#[MapCell(cast:CastToInt::class, options: ['default' => 42])]
private mixed $answerId;

#[MapCell(options: ['default' => 15.8])]
private ?float $temperature;
```

<p class="message-warning">When used with the <code>mixed</code> type or with an untyped property you are <strong>required</strong> to
specify to casting class otherwise the conversion will use the <code>CastToString</code> class instead.</p>

### CastToEnum

Convert the array value to a PHP `Enum`, it supports both unit and backed enumeration. The class takes two (2)
optionals arguments:

- `default` which is the default Enum value to return if the value is `null`.
- `className` which is the `Enum` to use for resolution if the property or method argument is untyped or typed as `mixed`.

If the `Enum` is backed the cell value will be considered as one of the `Enum` value; otherwise it will be used
as one the `Enum` name. The same logic applies for the `default` value. If the default value
is not `null` and the value given is incorrect, the mechanism will throw an exception.

```php
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\MapCell;

#[MapCell(
    column: 1,                // specify the record value via its offset
    cast: CastToEnum::class,  // explicitly specified because the argument is mixed
    options: ['default' => 'Abidjan', 'className' => Place::class]
)]
public function setPlace(mixed $place): void
{
    //apply the method logic whatever that is!
    //knowing that $place will be a Place enum instance
}
```

> convert the value of the array whose key is `1` into a `Place` Enum
> if the value is  null resolve the string `Abidjan` to `Place::Abidjan`. Once created,
> call the method `setPlace` with the created `Place` enum filling the `$place` argument.

<p class="notice">Using this class with a <code>mixed</code> type without providing the <code>className</code> parameter will trigger an exception.</p>

### CastToDate

Converts the cell value into a PHP `DateTimeInterface` implementing object. You can optionally specify:

- the date format via the `format` argument
- the date timezone if needed  via the `timezone` argument
- the `default` which is the default value to return if the value is `null`; should be `null` or a parsable date time `string`
- the `className` the class to use if the property is typed `mixed` or any interface that extends `DateTimeInterface`.

If the property is typed with:

- `mixed` or the `DateTimeInterface`, a `DateTimeImmutable` instance will be used if the `className` argument is not given.
- an interface that extends `DateTimInterface`, the `className` argument **MUST** be given.

```php
use League\Csv\Serializer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

#[Serializer\MapCell(
    options: [
        'className' => CarbonImmutable::class //must be specified because CarbonInterface is an interface
    ]
)]
private CarbonInterface $observedOn;
```

<p class="message-warning">Whenever the <code>className</code> argument is required but is invalid or missing an exception will be thrown.</p>

### CastToArray

Converts the value into a PHP `array`. You are required to specify the array shape for the conversion to happen. The class
provides three (3) shapes:

- `list` converts the string using PHP `explode` function by default the `separator` option is `,`;
- `csv` converts the string using PHP `str_getcsv` function with its default `delimiter` and `enclosure` options, the escape character is not available as its usage is not recommended to improve interoperability;
- `json` converts the string using PHP `json_decode` function with its default options;

The following are examples for each shape expected string value:

```php
$array['list'] = "1,2,3,4";         //the string contains only a delimiter (shape list)
$array['csv'] = '"1","2","3","4"';  //the string contains delimiter and enclosure (shape csv)
$array['json'] = '{"foo":"bar"}';   //the string is a json string (shape json)
```

Here's an example for casting a string via the `json` shape.

```php
use League\Csv\Serializer;

#[Serializer\MapCell(
    options: [
        'shape' => 'json',
        'flags' => JSON_BIGINT_AS_STRING
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

#[Serializer\MapCell(
    options: [
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

Three (3) mechanisms to extend typecasting are provided. You can register a callback via the `Denormalizer` class
or create a `League\Csv\Serializer\TypeCasting` implementing class. Of course, the choice will depend on your use case.

### Registering a type using a callback

You can register a callback using the `Denormalizer` class to convert a specific type. The type can be
any built-in type or a specific class. Once registered, the type will be automatically resolved using your
callback even during autodiscovery.

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

The `Denormalizer` will automatically call the callback for any `App\Domain\Money\Naira` conversion. You can
also use the `MapCell` attribute to further control the conversion

To do so specify your casting with the attribute:

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\MapCell(column: 'amount', options: ['default' => 1000_00])]
private ?Naira $amount;
```

<p class="message-notice">No need to specify the <code>cast</code> argument as the callback is registered.</p>

Using the callback mechanism you can redefine how to typecast to integer.

```php
use League\Csv\Serializer;

Serializer\Denormalizer::registerType('int', fn (?string $value): int => 42);
```

The callback takes precedence over the built-in `CastToInt` class to convert
to the `int` type during autodiscovery. You can still use the `CastToInt`
class, but you are now require to explicitly declare it via the `MapCell`
attribute using the `cast` argument.

The callback signature is the following:

```php
Closure(?string $value, bool $isNullable, ...$options): mixed;
```

where:

- the `$value` is the record value
- the `$isNullable` tells whether the argument or property is nullable
- the `$options` are the extra configuration options you can pass to the `MapCell` attribute via `options`

To complete the feature you can use:

- `Denormalizer::unregisterType` to remove a registered callback for a specific `type`
- `Denormalizer::unregisterAllTypes` to remove all registered callbacks for all types.
- `Denormalizer::types` to list all registered callbacks for all types. **new in 9.14.0**

```php
use League\Csv\Serializer;


Serializer\Denormalizer::unregisterType(Naira::class);
Serializer\Denormalizer::unregisterAllTypes();
Serializer\Denormalizer::types();
```

The three (3) methods are static.

<p class="message-notice">the callback mechanism does not support <code>IntersectionType</code></p>

### Registering a type alias using a callback

<p class="message-info">new in version <code>9.13.0</code></p>

If you want to provide alternative way to convert your string into a specific type you can instead register an alias.
Contrary to registering a type an alias :

- is not available during autodiscovery and needs to be specified using the `MapCell` attribute `cast` argument.
- does not take precedence over a type definition.

Registering an alias is similar to registering a type via callback:

```php
use League\Csv\Serializer;

Serializer\Denormalizer::registerAlias('@forty-two', 'int', fn (?string $value): int => 42);
```

The excepted callback argument follow the same signature and will be called exactly the same as with a type callback.

<p class="message-notice">The alias must start with an <code>@</code> character and contain alphanumeric (letters, numbers, regardless of case) plus underscore (_).</p>

Once generated you can use it as shown below:

```php
use App\Domain\Money
use League\Csv\Serializer;

#[Serializer\MapCell(column: 'amount', cast: '@forty-two')]
private ?int $amount;
```

It is possible to:

- unregister a specific alias using the `Denormalizer::unregisterAlias` method
- unregister all aliases using the `Denormalizer::unregisterAliases` method
- list all registered aliases using the `Denormalizer::aliases` method. The method returns an array with the alias as key and the type it is attached to as value.

```php
use League\Csv\Serializer;

Serializer\Denormalizer::unregisterAlias('@forty-two');
Serializer\Denormalizer::unregisterAllAliases();
```

<p class="message-info">If needed, can use the <code>Denormalizer::unregisterAll</code> to unregister all callbacks (alias and types)</p>

### Implementing a TypeCasting class

If you need to support `Intersection` type you need to provide your own class to typecast the value according
to your own rules. Since the class is not registered by default:

- you must configure its usage via the `MapCell` attribute `cast` argument
- it won't be available during autodiscovery.

```php
use App\Domain\Money\Naira;
use League\Csv\Serializer;

#[Serializer\MapCell(
    column: 'amount',
    cast: App\Domain\Money\CastToNaira::class,
    options: ['default' => 20_00]
)]
private ?Money $naira;
```

The `CastToNaira` will convert the cell value into a `Narai` object and if the value is `null`, `20_00` will be used.
To allow your object to cast the cell value to your liking it needs to implement the `TypeCasting` interface.

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
    public function __construct(
        ReflectionProperty|ReflectionParameter $reflectionProperty, //always given by the Denormalizer
    ) {
        // It is recommended to handle the $reflectionProperty argument.
        // The argument gives you access to property/argument information.
        // it allows validating that the argument does support your casting
        // it allows adding support to union, intersection or unnamed type 
        // it tells whether the property/argument is nullable or not.
        // in case of error you should throw a MappingFailed exception
    }

    public function setOptions(
        mixed ...$options //will be filled via the MapCell options array destructuring
    ): void {
        // in case of error you should throw a MappingFailed exception
    }

    public function toVariable(?string $value): ?Naira
    {
        //convert the Cell value into the expected type
        // in case of error you should throw a TypeCastingFailed exception
    }
}
```

<p class="message-info">While the built-in <code>TypeCasting</code> classes do not support Intersection Type, your own
implementing class can support them via inspection of the <code>$reflectionProperty</code> argument.</p>

<p class="message-notice">Don't hesitate to check the repository code source to see how each default
<code>TypeCasting</code> classes are implemented for reference.</p>

## Using the feature without a TabularDataReader

The feature can be used outside the package default usage via the `Denormalizer` class.

The class exposes four (4) methods to ease `array` to `object` denormalization:

- `Denormalizer::denormalizeAll` and `Denormalizer::assignAll` to convert a collection of records into a collection of instances of a specified class.
- `Denormalizer::denormalize` and `Denormalizer::assign` to convert a single record into a new instance of the specified class.

Since we are not leveraging the `TabularDataReader` feature we must explicitly tell the class how to link
array keys to class properties and/or methods. Once instantiated you can reuse the instance to independently
convert a single or a collection of similar `array`.

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

To complete the feature two (2) static methods are provided if you only need denormalization once.
`Denormalizer::assign` will automatically use the `array` keys as property names whereas,
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

Every rule and setting explain will apply to `Denormalizer` usage.
