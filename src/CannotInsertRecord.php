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

/**
 * Thrown when a data is not added to the Csv Document.
 */
class CannotInsertRecord extends Exception
{
    /** The record submitted for insertion. */
    protected array $record = [];
    /** Validator which did not validate the data. */
    protected string $name = '';

    /**
     * Creates an Exception from a record insertion into a stream.
     */
    public static function triggerOnInsertion(array $record): self
    {
        $exception = new self('Unable to write record to the CSV document');
        $exception->record = $record;

        return $exception;
    }

    /**
     * Creates an Exception from a Record Validation.
     */
    public static function triggerOnValidation(string $name, array $record): self
    {
        $exception = new self('Record validation failed');
        $exception->name = $name;
        $exception->record = $record;

        return $exception;
    }

    /**
     * Returns the validator name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the invalid data submitted.
     */
    public function getRecord(): array
    {
        return $this->record;
    }
}
