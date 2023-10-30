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

namespace League\Csv\Mapper;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class SerializerTest extends TestCase
{
    public function testItConvertsARecordsToAnObjectUsingProperties(): void
    {
        $record = [
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ];

        $serializer = new Serializer(WeatherProperty::class, ['date', 'temperature', 'place']);
        $weather = $serializer->deserialize($record);

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

        $serializer = new Serializer(WeatherSetterGetter::class, ['date', 'temperature', 'place']);
        $weather = $serializer->deserialize($record);

        self::assertInstanceOf(WeatherSetterGetter::class, $weather);
        self::assertSame('2023-10-30', $weather->getObservedOn()->format('Y-m-d'));
        self::assertSame(Place::Berkeley, $weather->place);
        self::assertSame(-1.5, $weather->getTemperature());
    }

    public function testItWillThrowIfTheHeaderIsMissingAndTheColumnOffsetIsAString(): void
    {
        $this->expectException(CellMappingFailed::class);
        $serializer = new Serializer(WeatherSetterGetter::class);
        $serializer->deserialize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheHeaderContainsInvalidOffsetName(): void
    {
        $this->expectException(CellMappingFailed::class);
        $serializer = new Serializer(WeatherSetterGetter::class, ['date', 'toto', 'foobar']);
        $serializer->deserialize([
            'date' => '2023-10-30',
            'temperature' => '-1.5',
            'place' => 'Berkeley',
        ]);
    }

    public function testItWillThrowIfTheColumnAttributesIsUsedMultipleTimeForTheSameAccessor(): void
    {
        $this->expectException(CellMappingFailed::class);

        new Serializer(InvalidWeatherAttributeUsage::class);
    }

    public function testItWillThrowIfTheColumnAttributesCasterIsInvalid(): void
    {
        $this->expectException(TypeError::class);

        new Serializer(InvalidWeatherAttributeCasterNotSupported::class);
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

enum Place: string
{
    case Galway = 'Galway';
    case Berkeley = 'Berkeley';
}

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
