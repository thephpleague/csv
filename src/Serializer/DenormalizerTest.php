<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv\Serializer;

use Countable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use League\Csv\Reader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use SplTempFileObject;
use stdClass;
use Traversable;

final class DenormalizerTest extends TestCase
{
    public function testItConvertsAnIterableListOfRecords(): void
    {
        $records = [
            [
                'date' => '2023-10-30',
                'temperature' => '-1.5',
                'place' => 'Abidjan',
            ],
            [
                'date' => '2023-10-31',
                'temperature' => '-3',
                'place' => 'Abidjan',
            ],
        ];

        $class = new class (5, Place::Yamoussokro, new DateTimeImmutable()) {
            public function __construct(
                public readonly float $temperature,
                public readonly Place $place,
                #[MapCell(
                    column: 'date',
                    options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $results = [...Denormalizer::assignAll($class::class, $records, ['date', 'temperature', 'place'])];
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertInstanceOf($class::class, $result);
        }
    }

    public function testItConvertsARecordsToAnObjectUsingRecordAttribute(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Abidjan',
        ];

        $class = new class (5, Place::Yamoussokro, new DateTimeImmutable()) {
            public function __construct(
                public readonly float $temperature,
                public readonly Place $place,
                #[MapCell(
                    column: 'date',
                    options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $weather = Denormalizer::assign($class::class, $record);

        self::assertInstanceOf($class::class, $weather);
        self::assertInstanceOf(DateTimeImmutable::class, $weather->observedOn);
        self::assertSame(Place::Abidjan, $weather->place);
        self::assertSame(-1.5, $weather->temperature);
    }

    public function testItConvertsARecordsToAnObjectUsingProperties(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Abidjan',
        ];

        $foobar = new class (3, Place::Yamoussokro, new DateTimeImmutable()) {
            public function __construct(
                #[MapCell(column:'temperature')]
                public readonly float $temperature,
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column:'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $weather = Denormalizer::assign($foobar::class, $record);

        self::assertInstanceOf($foobar::class, $weather);
        self::assertInstanceOf(DateTimeImmutable::class, $weather->observedOn);
        self::assertSame(Place::Abidjan, $weather->place);
        self::assertSame(-1.5, $weather->temperature);
    }

    public function testItConvertsARecordsToAnObjectUsingMethods(): void
    {
        $class = new class (Place::Abidjan, new DateTime()) {
            private float $temperature;

            public function __construct(
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column: 'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                private readonly DateTime $observedOn
            ) {
            }

            #[MapCell(column:'temperature')]
            public function setTemperature(float $temperature): void
            {
                $this->temperature = $temperature;
            }

            public function getTemperature(): float
            {
                return $this->temperature;
            }

            public function getObservedOn(): DateTime
            {
                return $this->observedOn;
            }
        };

        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Abidjan',
        ];

        $weather = Denormalizer::assign($class::class, $record);

        self::assertInstanceOf($class::class, $weather);
        self::assertSame('2023-10-30', $weather->getObservedOn()->format('Y-m-d'));
        self::assertSame(Place::Abidjan, $weather->place);
        self::assertSame(-1.5, $weather->getTemperature());
    }

    public function testMappingFailBecauseTheRecordAttributeIsMissing(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('No property or method from `stdClass` could be used for denormalization.');

        Denormalizer::assign(stdClass::class, ['foo' => 'bar']);
    }

    public function testItWillThrowIfTheHeaderIsMissingAndTheColumnOffsetIsAString(): void
    {
        $class = new class (Place::Abidjan, new DateTime()) {
            private float $temperature;

            public function __construct(
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column: 'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                private readonly DateTime $observedOn
            ) {
            }

            #[MapCell(column:'temperature')]
            public function setTemperature(float $temperature): void
            {
                $this->temperature = $temperature;
            }

            public function getTemperature(): float
            {
                return $this->temperature;
            }

            public function getObservedOn(): DateTime
            {
                return $this->observedOn;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('offset as string are only supported if the property names list is not empty.');

        $serializer = new Denormalizer($class::class);
        $serializer->denormalize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Abidjan',
        ]);
    }

    public function testItWillThrowIfTheHeaderContainsInvalidOffsetName(): void
    {
        $class = new class (Place::Abidjan, new DateTime()) {
            private float $temperature;

            public function __construct(
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column: 'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                private readonly DateTime $observedOn
            ) {
            }

            #[MapCell(column:'temperature')]
            public function setTemperature(float $temperature): void
            {
                $this->temperature = $temperature;
            }

            public function getTemperature(): float
            {
                return $this->temperature;
            }

            public function getObservedOn(): DateTime
            {
                return $this->observedOn;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The `temperature` property could not be found in the property names list; Please verify your property names list.');

        $serializer = new Denormalizer($class::class, ['date', 'toto', 'foobar']);
        $serializer->denormalize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Abidjan',
        ]);
    }

    public function testItWillThrowIfTheColumnAttributesIsUsedMultipleTimeForTheSameAccessor(): void
    {
        $class = new class (5, Place::Abidjan, new DateTimeImmutable()) {
            public function __construct(
                #[MapCell(column:'temperature'), MapCell(column:'date')] /* @phpstan-ignore-line */
                public readonly float $temperature,
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column: 'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('Using more than one `'.MapCell::class.'` attribute on a class property or method is not supported.');

        new Denormalizer($class::class);
    }

    public function testItWillThrowIfTheColumnAttributesCasterIsInvalid(): void
    {
        $foobar = new class (5, Place::Yamoussokro, new DateTimeImmutable()) {
            public function __construct(
                #[MapCell(column:'temperature', cast: stdClass::class)]
                public readonly float $temperature,
                #[MapCell(column:2)]
                public readonly Place $place,
                #[MapCell(column: 'date', options: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'])]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('`stdClass` must be an resolvable class implementing the `League\Csv\Serializer\TypeCasting` interface or a supported alias.');

        new Denormalizer($foobar::class);
    }

    public function testItWillResolveTheObjectWhichDoesNotHaveTypedPropertiesUsingCellAttribute(): void
    {
        $foobar = new class (3, Place::Abidjan, new DateTimeImmutable()) {
            /* @phpstan-ignore-next-line */
            public function __construct(
                #[MapCell(cast:CastToFloat::class)]
                public $temperature,
                #[MapCell(cast:CastToEnum::class, options: ['className' => Place::class])]
                public $place,
                #[MapCell(cast: CastToDate::class)]
                public $observedOn
            ) {
            }
        };

        $instance = Denormalizer::assign($foobar::class, ['temperature' => '1', 'place' => 'Abidjan', 'observedOn' => '2023-10-23']);

        self::assertInstanceOf($foobar::class, $instance);
        self::assertSame(1.0, $instance->temperature);
        self::assertSame(Place::Abidjan, $instance->place);
        self::assertEquals(new DateTimeImmutable('2023-10-23'), $instance->observedOn);

    }

    public function testItWillFailForLackOfTypeCasting(): void
    {
        $foobar = new class (5, 'Yamoussokro', new SplTempFileObject()) {
            public function __construct(
                public int $temperature,
                public string $place,
                public SplFileObject $observedOn
            ) {
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property type definition for `'.$foobar::class.'::observedOn` is missing; register it using the `'.Denormalizer::class.'` class.');

        new Denormalizer($foobar::class, ['temperature', 'place', 'observedOn']);
    }

    public function testItWillCallAMethodAfterMapping(): void
    {
        $usingAfterMapping = new #[MapRecord(afterMapping: ['addOne'])] class (23) {
            public function __construct(public int $addition)
            {
                $this->addOne();
            }

            private function addOne(): void
            {
                ++$this->addition;
            }
        };

        /** @var object{addition: int} $res */
        $res = Denormalizer::assign($usingAfterMapping::class, ['addition' => '1']);

        self::assertSame(2, $res->addition);
    }

    public function testIfFailsToUseAfterMappingWithUnknownMethod(): void
    {
        $missingMethodAfterMapping = new #[MapRecord(afterMapping: ['addOne', 'addTow'])] class (23) {
            public function __construct(public int $addition)
            {
                $this->addOne();
            }

            private function addOne(): void
            {
                ++$this->addition;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The method `addTow` is not defined on the `'.$missingMethodAfterMapping::class.'` class.');

        Denormalizer::assign($missingMethodAfterMapping::class, ['addition' => '1']);
    }

    public function testIfFailsToUseAfterMappingWithInvalidArgument(): void
    {
        $requiresArgumentAfterMapping = new #[MapRecord(afterMapping: ['addOne'])] class (23) {
            public function __construct(public int $addition)
            {
                $this->addOne(1);
            }

            private function addOne(int $add): void
            {
                $this->addition += $add;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The method `'.$requiresArgumentAfterMapping::class.'::addOne` has too many required parameters.');

        Denormalizer::assign($requiresArgumentAfterMapping::class, ['addition' => '1']);
    }

    public function testItWillThrowIfTheClassContainsUninitializedProperties(): void
    {
        $foobar = new class ('prenoms', 18, 'M', new SplTempFileObject()) {
            public function __construct(
                public readonly string $prenoms,
                private readonly int $nombre,
                public readonly string $sexe,
                #[MapCell(options: ['format' => '!Y'])]
                public SplFileObject $annee
            ) {
            }

            public function getNombre(): int
            {
                return $this->nombre;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property type definition for `'.$foobar::class.'::annee` is missing; register it using the `'.Denormalizer::class.'` class.');

        Denormalizer::assign(
            $foobar::class,
            ['prenoms' => 'John', 'nombre' => '42', 'sexe' => 'M', 'annee' => '2018']
        );
    }

    public function testItCanNotAutodiscoverWithIntersectionType(): void
    {
        $foobar = new class () {
            public Countable&Traversable $traversable;
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property type definition for `'.$foobar::class.'::traversable` is missing; register it using the `'.Denormalizer::class.'` class.');

        Denormalizer::assign($foobar::class, ['traversable' => '1']);
    }

    public function testItWillThrowIfThePropertyIsMisMatchWithTheTypeCastingClass(): void
    {
        $foobar = new class () {
            private string $firstName;
            #[MapCell(cast: CastToDate::class)]
            public function setFirstName(string $firstName): void
            {
                $this->firstName = $firstName;
            }

            public function getFirstName(): string
            {
                return $this->firstName;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The type for the method `'.$foobar::class.'::setFirstName` first argument `firstName` is invalid; `DateTimeInterface` or `mixed` type must be used with the `League\Csv\Serializer\CastToDate`.');

        Denormalizer::assign($foobar::class, ['firstName' => 'john']);
    }

    public function testItCanUseTheClosureRegisteringMechanism(): void
    {
        $record = ['foo' => 'toto'];
        $foobar = new class () {
            public string $foo;
        };

        Denormalizer::registerType('string', fn (?string $value) => 'yolo!');

        self::assertSame('yolo!', Denormalizer::assign($foobar::class, $record)->foo); /* @phpstan-ignore-line */

        Denormalizer::unregisterType('string');

        self::assertSame('toto', Denormalizer::assign($foobar::class, $record)->foo);
    }

    public function testItFailsToRegisterUnknownType(): void
    {
        $type = 'UnkownType';
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The `'.$type.'` could not be register.');

        Denormalizer::registerType($type, fn (?string $value) => 'yolo!');
    }

    public function testEmptyStringHandling(): void
    {
        $record = ['foo' => ''];
        $foobar = new class () {
            public ?string $foo;
        };

        Denormalizer::disallowEmptyStringAsNull(); /* @phpstan-ignore-line */

        self::assertSame('', Denormalizer::assign($foobar::class, $record)->foo); /* @phpstan-ignore-line */

        Denormalizer::allowEmptyStringAsNull(); /* @phpstan-ignore-line */

        self::assertNull(Denormalizer::assign($foobar::class, $record)->foo);
    }

    public function testResolvesMethodWithUntypedParameterToStringByDefaultUsingCell(): void
    {
        $class = new class () {
            private ?string $foobar;
            #[MapCell] /** @phpstan-ignore-line  */
            public function setFoobar($foobar): void
            {
                $this->foobar = $foobar;
            }

            public function getFoobar(): ?string
            {
                return $this->foobar;
            }
        };

        $instance = Denormalizer::assign($class::class, ['foobar' => 'barbaz']);
        $instance1 = Denormalizer::assign($class::class, ['foobar' => null]);

        self::assertInstanceOf($class::class, $instance);
        self::assertSame('barbaz', $instance->getFoobar());

        self::assertInstanceOf($class::class, $instance1);
        self::assertNull($instance1->getFoobar());
    }

    public function testItWillAutoDiscoverThePublicMethod(): void
    {
        $class = new class () {
            private DateTimeInterface $foo;

            public function setDate(string $toto): void
            {
                $this->foo = new DateTimeImmutable($toto, new DateTimeZone('Africa/Abidjan'));
            }

            public function getDate(): DateTimeInterface
            {
                return $this->foo;
            }
        };

        $object = Denormalizer::assign($class::class, ['date' => 'tomorrow']);
        self::assertInstanceOf($class::class, $object);
        self::assertEquals(new DateTimeZone('Africa/Abidjan'), $object->getDate()->getTimezone());
    }

    public function testItFailToAutoDiscoverThePublicMethodWithMoreThanOneRequiredArgument(): void
    {
        $class = new class () {
            private DateTimeInterface $foo;

            public function setDate(string $toto, string $timezone): void
            {
                $this->foo = new DateTimeImmutable($toto, new DateTimeZone($timezone));
            }

            public function getDate(): DateTimeInterface
            {
                return $this->foo;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('No property or method from `'.$class::class.'` could be used for denormalization.');

        Denormalizer::assign($class::class, ['date' => 'tomorrow']);
    }

    #[Test]
    public function it_can_use_aliases(): void
    {
        self::assertSame([], Denormalizer::aliases());
        self::assertFalse(Denormalizer::supportsAlias('@strtoupper'));

        Denormalizer::registerAlias('@strtoupper', 'string', fn (?string $str) => null === $str ? '' : strtoupper($str));

        self::assertSame(['@strtoupper' => 'string'], Denormalizer::aliases());
        self::assertTrue(Denormalizer::supportsAlias('@strtoupper'));

        $class = new class ('toto') {
            public function __construct(
                #[MapCell(cast: '@strtoupper')]
                public readonly string $str
            ) {
            }
        };

        $instance = Denormalizer::assign($class::class, ['str' => 'kinshasa']);

        self::assertInstanceOf($class::class, $instance);
        self::assertSame('KINSHASA', $instance->str);

        self::assertTrue(Denormalizer::unregisterAlias('@strtoupper'));
        self::assertFalse(Denormalizer::unregisterAlias('@strtoupper'));

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('`@strtoupper` must be an resolvable class implementing the `'.TypeCasting::class.'` interface or a supported alias.');
        Denormalizer::assign($class::class, ['str' => 'kinshasa']);
    }

    #[Test]
    public function it_will_fail_to_registered_an_invalid_alias_name(): void
    {
        $invalidAlias = 'invalidAlias';

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage("The alias `$invalidAlias` is invalid. It must start with an `@` character and contain alphanumeric (letters, numbers, regardless of case) plus underscore (_).");

        Denormalizer::registerAlias($invalidAlias, 'string', fn (?string $str) => null === $str ? '' : strtoupper($str));
    }

    #[Test]
    public function it_will_fail_to_registered_twice_the_same_alias(): void
    {
        $validAlias = '@alias';

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The alias `'.$validAlias.'` is already registered. Please choose another name.');

        Denormalizer::registerAlias($validAlias, 'string', fn (?string $str) => null === $str ? '' : strtoupper($str));
        Denormalizer::registerAlias($validAlias, 'int', fn (?string $str) => null === $str ? '' : strtoupper($str));
    }

    #[Test]
    public function it_will_succeed_with_an_alias_on_an_untyped_property(): void
    {
        Denormalizer::registerAlias('@lowercase', 'string', fn (?string $str) => strtolower((string) $str));

        $class = new class ('toto') {
            /**
             * @param ?string $str
             */
            public function __construct(
                #[MapCell(column:'place', cast: '@lowercase')]
                public $str
            ) {
            }
        };

        $instance = Denormalizer::assign($class::class, ['place' => 'YaMouSSokro']);
        self::assertInstanceOf($class::class, $instance);
        self::assertSame('yamoussokro', $instance->str);
    }

    #[Test]
    public function it_will_ignore_the_property_during_auto_discovery(): void
    {
        $classIgnoreMethod = new class () {
            public DateTimeInterface $observedOn;

            #[MapCell(ignore: true)]
            public function setObservedOn(DateTimeInterface $observedOn): void
            {
                $this->observedOn = DateTime::createFromInterface($observedOn);
            }
        };

        $instance = Denormalizer::assign($classIgnoreMethod::class, ['observedOn' => '2023-10-01']);

        self::assertInstanceOf($classIgnoreMethod::class, $instance);
        self::assertInstanceOf(DateTimeImmutable::class, $instance->observedOn);

        $classIgnoreProperty = new class () {
            #[MapCell(ignore: true)]
            public DateTimeInterface $observedOn;

            public function setObservedOn(DateTimeInterface $observedOn): void
            {
                $this->observedOn = DateTime::createFromInterface($observedOn);
            }
        };

        $instance = Denormalizer::assign($classIgnoreProperty::class, ['observedOn' => '2023-10-01']);

        self::assertInstanceOf($classIgnoreProperty::class, $instance);
        self::assertInstanceOf(DateTime::class, $instance->observedOn);
    }

    #[Test]
    public function it_will_tell_whether_the_type_or_alias_is_supported(): void
    {
        Denormalizer::registerType(SplFileObject::class, fn (?string $value) => new SplFileObject((string) $value, 'r'));
        Denormalizer::registerAlias('@file', SplTempFileObject::class, function (?string $value) {
            $file = new SplTempFileObject();
            $file->fwrite((string) $value);

            return $file;
        });

        self::assertTrue(Denormalizer::supportsAlias('@file'));
    }

    #[Test]
    public function it_will_fails_if_the_property_is_missing_from_source(): void
    {
        $data = ['foo' => 'bar'];

        $class = new class () {
            public string $foo;
            public string $bar;
        };

        $this->expectException(DenormalizationFailed::class);
        $this->expectExceptionMessage('The property '.$class::class.'::bar is not initialized; its value is missing from the source data.');

        Denormalizer::assign($class::class, $data);
    }

    #[Test]
    public function it_will_trim_white_space_on_request(): void
    {
        $csv = <<<CSV
id,title,description
 23 , foobar  , je suis trop fort
CSV;
        $item = new #[MapRecord(trimFieldValueBeforeCasting: true)] class (23, 'foobar', ' je suis trop fort') {
            public function __construct(
                public int $id,
                public string $title,
                #[MapCell(trimFieldValueBeforeCasting: false)]
                public string $description,
            ) {
            }
        };

        $document = Reader::createFromString($csv);
        $document->setHeaderOffset(0);

        self::assertEquals($item, $document->firstAsObject($item::class));
    }
}

enum Place: string
{
    case Yamoussokro = 'Yamoussokro';
    case Abidjan = 'Abidjan';
}
