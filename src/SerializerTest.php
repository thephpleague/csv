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

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use League\Csv\Serializer\CastToDate;
use League\Csv\Serializer\CastToEnum;
use League\Csv\Serializer\Cell;
use League\Csv\Serializer\MappingFailed;
use League\Csv\Serializer\Record;
use PHPUnit\Framework\TestCase;
use SplFileObject;
use stdClass;
use TypeError;

final class SerializerTest extends TestCase
{
    public function testItConvertsARecordsToAnObjectUsingRecordAttribute(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ];

        $weather = Serializer::map(WeatherWithRecordAttribute::class, $record);

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

        $weather = Serializer::map(WeatherProperty::class, $record);

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

        $weather = Serializer::map(WeatherSetterGetter::class, $record);

        self::assertInstanceOf(WeatherSetterGetter::class, $weather);
        self::assertSame('2023-10-30', $weather->getObservedOn()->format('Y-m-d'));
        self::assertSame(Place::Berkeley, $weather->place);
        self::assertSame(-1.5, $weather->getTemperature());
    }

    public function testItWillThrowIfTheHeaderIsMissingAndTheColumnOffsetIsAString(): void
    {
        $this->expectException(MappingFailed::class);
        $serializer = new Serializer(WeatherSetterGetter::class);
        $serializer->deserialize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheHeaderContainsInvalidOffsetName(): void
    {
        $this->expectException(MappingFailed::class);

        $serializer = new Serializer(WeatherSetterGetter::class, ['date', 'toto', 'foobar']);
        $serializer->deserialize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheColumnAttributesIsUsedMultipleTimeForTheSameAccessor(): void
    {
        $this->expectException(MappingFailed::class);

        new Serializer(InvalidWeatherAttributeUsage::class);
    }

    public function testItWillThrowIfTheColumnAttributesCasterIsInvalid(): void
    {
        $this->expectException(TypeError::class);

        new Serializer(InvalidWeatherAttributeCasterNotSupported::class);
    }

    public function testItWillThrowBecauseTheObjectDoesNotHaveTypedProperties(): void
    {
        $this->expectException(MappingFailed::class);

        new Serializer(InvaliDWeatherWithRecordAttribute::class, ['temperature', 'foobar', 'observedOn']);
    }

    public function testItWillThrowIfTheClassContainsUnitiliaziedProperties(): void
    {
        $this->expectException(MappingFailed::class);
        $this->expectExceptionMessage('The property '.InvalidObjectWithUninitializedProperty::class.'::nombre is not initialized.');

        $serializer = new Serializer(InvalidObjectWithUninitializedProperty::class, ['prenoms', 'nombre', 'sexe', 'annee']);
        $serializer->deserialize(['prenoms' => 'John', 'nombre' => 42, 'sexe' => 'M', 'annee' => '2018']);
    }
}

enum Place: string
{
    case Galway = 'Galway';
    case Berkeley = 'Berkeley';
}

#[Record]
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

#[Record]
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

#[Record]
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

#[Record]
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

#[Record]
final class InvalidWeatherAttributeUsage
{
    public function __construct(
        /* @phpstan-ignore-next-line */
        #[Cell(offset:'temperature'), Cell(offset:'date')]
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

#[Record]
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

#[Record]
final class InvalidObjectWithUninitializedProperty
{
    public function __construct(
        public readonly string $prenoms,
        private readonly int $nombre,
        public readonly string $sexe,
        #[Cell(castArguments: ['format' => '!Y'])]
        public readonly DateTimeInterface $annee
    ) {
    }

    public function nomber(): int
    {
        return $this->nombre;
    }
}
