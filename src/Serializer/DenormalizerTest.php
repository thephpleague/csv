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
                #[Cell(
                    column: 'date',
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
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
                #[Cell(
                    column: 'date',
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
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
                #[Cell(column:'temperature')]
                public readonly float $temperature,
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
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
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                private readonly DateTime $observedOn
            ) {
            }

            #[Cell(column:'temperature')]
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
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                private readonly DateTime $observedOn
            ) {
            }

            #[Cell(column:'temperature')]
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
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                private readonly DateTime $observedOn
            ) {
            }

            #[Cell(column:'temperature')]
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
                #[Cell(column:'temperature'), Cell(column:'date')] /* @phpstan-ignore-line */
                public readonly float $temperature,
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('Using more than one `League\Csv\Serializer\Cell` attribute on a class property or method is not supported.');

        new Denormalizer($class::class);
    }

    public function testItWillThrowIfTheColumnAttributesCasterIsInvalid(): void
    {
        $foobar = new class (5, Place::Yamoussokro, new DateTimeImmutable()) {
            public function __construct(
                #[Cell(column:'temperature', cast: stdClass::class)]
                public readonly float $temperature,
                #[Cell(column:2, cast: CastToEnum::class)]
                public readonly Place $place,
                #[Cell(
                    column: 'date',
                    cast: CastToDate::class,
                    castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
                )]
                public readonly DateTimeInterface $observedOn
            ) {
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('`stdClass` must be an resolvable class implementing the `League\Csv\Serializer\TypeCasting` interface.');

        new Denormalizer($foobar::class);
    }

    public function testItWillResolveTheObjectWhichDoesNotHaveTypedPropertiesUsingCellAttribute(): void
    {
        $foobar = new class (3, Place::Abidjan, new DateTimeImmutable()) {
            /* @phpstan-ignore-next-line */
            public function __construct(
                #[Cell(cast:CastToFloat::class)]
                public $temperature,
                #[Cell(cast:CastToEnum::class, castArguments: ['className' => Place::class])]
                public $place,
                #[Cell(cast: CastToDate::class)]
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
        $this->expectExceptionMessage('The property type for `'.$foobar::class.'::observedOn` is missing or is not supported.');

        new Denormalizer($foobar::class, ['temperature', 'place', 'observedOn']);
    }

    public function testItWillThrowIfTheClassContainsUninitializedProperties(): void
    {
        $foobar = new class ('prenoms', 18, 'M', new SplTempFileObject()) {
            public function __construct(
                public readonly string $prenoms,
                private readonly int $nombre,
                public readonly string $sexe,
                #[Cell(castArguments: ['format' => '!Y'])]
                public SplFileObject $annee
            ) {
            }

            public function getNombre(): int
            {
                return $this->nombre;
            }
        };

        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property type for `'.$foobar::class.'::annee` is missing or is not supported.');

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
        $this->expectExceptionMessage('The property type for `'.$foobar::class.'::traversable` is missing or is not supported.');

        Denormalizer::assign($foobar::class, ['traversable' => '1']);
    }

    public function testItWillThrowIfThePropertyIsMisMatchWithTheTypeCastingClass(): void
    {
        $foobar = new class () {
            private string $firstName;
            #[Cell(cast: CastToDate::class)]
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

        Denormalizer::disallowEmptyStringAsNull();

        self::assertSame('', Denormalizer::assign($foobar::class, $record)->foo); /* @phpstan-ignore-line */

        Denormalizer::allowEmptyStringAsNull();

        self::assertNull(Denormalizer::assign($foobar::class, $record)->foo);
    }

    public function testResolvesMethodWithUntypedParameterToStringByDefaultUsingCell(): void
    {
        $class = new class () {
            private ?string $foobar;
            #[Cell] /** @phpstan-ignore-line  */
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
}

enum Place: string
{
    case Yamoussokro = 'Yamoussokro';
    case Abidjan = 'Abidjan';
}
