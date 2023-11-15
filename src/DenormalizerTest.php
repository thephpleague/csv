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

namespace League\Csv;

use Countable;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\Cell;
use League\Csv\Serializer\MappingFailed;
use PHPUnit\Framework\TestCase;
use SplFileObject;
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
                'place' => 'Berkeley',
            ],
            [
                'date' => '2023-10-31',
                'temperature' => '-3',
                'place' => 'Berkeley',
            ],
        ];

        $results = [...Denormalizer::assignAll(WeatherWithRecordAttribute::class, $records, ['date', 'temperature', 'place'])];
        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertInstanceOf(WeatherWithRecordAttribute::class, $result);
        }
    }

    public function testItConvertsARecordsToAnObjectUsingRecordAttribute(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ];

        $weather = Denormalizer::assign(WeatherWithRecordAttribute::class, $record);

        self::assertInstanceOf(WeatherWithRecordAttribute::class, $weather);
        self::assertInstanceOf(DateTimeImmutable::class, $weather->observedOn);
        self::assertSame(Place::Berkeley, $weather->place);
        self::assertSame(-1.5, $weather->temperature);
    }

    public function testItConvertsARecordsToAnObjectUsingProperties(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ];

        $weather = Denormalizer::assign(WeatherProperty::class, $record);

        self::assertInstanceOf(WeatherProperty::class, $weather);
        self::assertInstanceOf(DateTimeImmutable::class, $weather->observedOn);
        self::assertSame(Place::Berkeley, $weather->place);
        self::assertSame(-1.5, $weather->temperature);
    }

    public function testItConvertsARecordsToAnObjectUsingMethods(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ];

        $weather = Denormalizer::assign(WeatherSetterGetter::class, $record);

        self::assertInstanceOf(WeatherSetterGetter::class, $weather);
        self::assertSame('2023-10-30', $weather->getObservedOn()->format('Y-m-d'));
        self::assertSame(Place::Berkeley, $weather->place);
        self::assertSame(-1.5, $weather->getTemperature());
    }

    public function testMappingFailBecauseTheRecordAttributeIsMissing(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('No property or method from `stdClass` can be used for deserialization.');

        Denormalizer::assign(stdClass::class, ['foo' => 'bar']);
    }

    public function testItWillThrowIfTheHeaderIsMissingAndTheColumnOffsetIsAString(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('Column name as string are only supported if the tabular data has a non-empty header.');

        $serializer = new Denormalizer(WeatherSetterGetter::class);
        $serializer->denormalize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheHeaderContainsInvalidOffsetName(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The offset `temperature` could not be found in the header; Pleaser verify your header data.');

        $serializer = new Denormalizer(WeatherSetterGetter::class, ['date', 'toto', 'foobar']);
        $serializer->denormalize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheColumnAttributesIsUsedMultipleTimeForTheSameAccessor(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('Using more than one `League\Csv\Serializer\Cell` attribute on a class property or method is not supported.');

        new Denormalizer(InvalidWeatherAttributeUsage::class);
    }

    public function testItWillThrowIfTheColumnAttributesCasterIsInvalid(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('`stdClass` must be an resolvable class implementing the `League\Csv\Serializer\TypeCasting` interface.');

        new Denormalizer(InvalidWeatherAttributeCasterNotSupported::class);
    }

    public function testItWillThrowBecauseTheObjectDoesNotHaveTypedProperties(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property `temperature` must be typed with a supported type.');

        new Denormalizer(InvaliDWeatherWithRecordAttribute::class, ['temperature', 'foobar', 'observedOn']);
    }

    public function testItWillFailForLackOfTypeCasting(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property `observedOn` must be typed with a supported type.');

        new Denormalizer(InvaliDWeatherWithRecordAttributeAndUnknownCasting::class, ['temperature', 'place', 'observedOn']);
    }

    public function testItWillThrowIfTheClassContainsUninitializedProperties(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property `annee` must be typed with a supported type.');

        Denormalizer::assign(
            InvalidObjectWithUninitializedProperty::class,
            ['prenoms' => 'John', 'nombre' => '42', 'sexe' => 'M', 'annee' => '2018']
        );
    }

    public function testItCanNotAutodiscoverWithIntersectionType(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property `traversable` must be typed with a supported type.');

        $foobar = new class () {
            public Countable&Traversable $traversable;
        };

        Denormalizer::assign($foobar::class, ['traversable' => '1']);
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
}

enum Place: string
{
    case Galway = 'Galway';
    case Berkeley = 'Berkeley';
}

final class InvaliDWeatherWithRecordAttribute
{
    /* @phpstan-ignore-next-line */
    public function __construct(
        public $temperature,
        public $place,
        public SplFileObject $observedOn
    ) {
    }
}

final class InvaliDWeatherWithRecordAttributeAndUnknownCasting
{
    public function __construct(
        public int $temperature,
        public string $place,
        public SplFileObject $observedOn
    ) {
    }
}

final class WeatherWithRecordAttribute
{
    public function __construct(
        public readonly float $temperature,
        public readonly Place $place,
        #[Cell(
            offset: 'date',
            castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
        )]
        public readonly DateTimeInterface $observedOn
    ) {
    }
}

final class WeatherProperty
{
    public function __construct(
        #[Cell(offset:'temperature')]
        public readonly float $temperature,
        #[Cell(offset:2, cast: CastToEnum::class)]
        public readonly Place $place,
        #[Cell(
            offset: 'date',
            cast: CastToDate::class,
            castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
        )]
        public readonly DateTimeInterface $observedOn
    ) {
    }
}

final class WeatherSetterGetter
{
    private float $temperature;

    public function __construct(
        #[Cell(offset:2, cast: CastToEnum::class)]
        public readonly Place $place,
        #[Cell(
            offset: 'date',
            cast: CastToDate::class,
            castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
        )]
        private readonly DateTime $observedOn
    ) {
    }

    #[Cell(offset:'temperature')]
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
}

final class InvalidWeatherAttributeUsage
{
    public function __construct(
        #[Cell(offset:'temperature'), Cell(offset:'date')] /* @phpstan-ignore-line */
        public readonly float $temperature,
        #[Cell(offset:2, cast: CastToEnum::class)]
        public readonly Place $place,
        #[Cell(
            offset: 'date',
            cast: CastToDate::class,
            castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
        )]
        public readonly DateTimeInterface $observedOn
    ) {
    }
}

final class InvalidWeatherAttributeCasterNotSupported
{
    public function __construct(
        #[Cell(offset:'temperature', cast: stdClass::class)]
        public readonly float $temperature,
        #[Cell(offset:2, cast: CastToEnum::class)]
        public readonly Place $place,
        #[Cell(
            offset: 'date',
            cast: CastToDate::class,
            castArguments: ['format' => '!Y-m-d', 'timezone' => 'Africa/Kinshasa'],
        )]
        public readonly DateTimeInterface $observedOn
    ) {
    }
}

final class InvalidObjectWithUninitializedProperty
{
    public function __construct(
        public readonly string $prenoms,
        private readonly int $nombre,
        public readonly string $sexe,
        #[Cell(castArguments: ['format' => '!Y'])]
        public SplFileObject $annee
    ) {
    }

    public function nombre(): int
    {
        return $this->nombre;
    }
}
