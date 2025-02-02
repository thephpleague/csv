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

use Closure;
use Deprecated;
use Dom\HTMLDocument;
use Dom\HTMLElement;
use Dom\XMLDocument;
use DOMDocument;
use DOMElement;
use DOMException;

use function is_bool;
use function preg_match;

/**
 * Converts tabular data into an HTML Table string.
 */
class HTMLConverter
{
    /** table class attribute value. */
    protected string $class_name = 'table-csv-data';
    /** table id attribute value. */
    protected string $id_value = '';
    /** @var ?Closure(array, array-key): array */
    protected ?Closure $formatter = null;
    protected string $offset_attr = '';
    protected string $column_attr = '';

    private static function supportsModernDom(): bool
    {
        return extension_loaded('dom') && class_exists(HTMLDocument::class);
    }

    public function __construct()
    {
    }

    /**
     * Converts a tabular data collection into an HTML table string.
     *
     * @param array<string> $header_record An optional array of headers outputted using the `<thead>` and `<th>` elements
     * @param array<string> $footer_record An optional array of footers outputted using the `<tfoot>` and `<th>` elements
     */
    public function convert(iterable $records, array $header_record = [], array $footer_record = []): string
    {
        if (null !== $this->formatter) {
            $records = MapIterator::fromIterable($records, $this->formatter);
        }

        $document = self::supportsModernDom() ? HTMLDocument::createEmpty() : new DOMDocument('1.0');
        $table = $document->createElement('table');
        if ('' !== $this->class_name) {
            $table->setAttribute('class', $this->class_name);
        }

        if ('' !== $this->id_value) {
            $table->setAttribute('id', $this->id_value);
        }

        $this->appendHeaderSection('thead', $header_record, $table);
        $this->appendHeaderSection('tfoot', $footer_record, $table);

        $tbody = $table;
        if ($table->hasChildNodes()) {
            $tbody = $document->createElement('tbody');
            $table->appendChild($tbody);
        }

        foreach ($records as $offset => $record) {
            $tr = $document->createElement('tr');
            if ('' !== $this->offset_attr) {
                $tr->setAttribute($this->offset_attr, (string) $offset);
            }

            foreach ($record as $field_name => $field_value) {
                $td = $document->createElement('td');
                if ('' !== $this->column_attr) {
                    $td->setAttribute($this->column_attr, (string) $field_name);
                }
                $td->appendChild($document->createTextNode($field_value));
                $tr->appendChild($td);
            }

            $tbody->appendChild($tr);
        }

        $document->appendChild($table);

        return (string) $document->saveHTML($table);
    }

    /**
     * Creates a DOMElement representing an HTML table heading section.
     *
     * @throws DOMException
     */
    protected function appendHeaderSection(string $node_name, array $record, DOMElement|HTMLElement $table): void
    {
        if ([] === $record) {
            return;
        }

        /** @var DOMDocument|HTMLDocument $document */
        $document = $table->ownerDocument;
        $header = $document->createElement($node_name);
        $tr = $document->createElement('tr');
        foreach ($record as $field_value) {
            $th = $document->createElement('th');
            $th->setAttribute('scope', 'col');
            $th->appendChild($document->createTextNode($field_value));
            $tr->appendChild($th);
        }

        $header->appendChild($tr);
        $table->appendChild($header);
    }

    /**
     * HTML table class name setter.
     *
     * @throws DOMException if the id_value contains any type of whitespace
     */
    public function table(string $class_name, string $id_value = ''): self
    {
        1 !== preg_match(",\s,", $id_value) || throw new DOMException("The id attribute's value must not contain whitespace (spaces, tabs etc.)");

        $clone = clone $this;
        $clone->class_name = $class_name;
        $clone->id_value = $id_value;

        return $clone;
    }

    /**
     * HTML tr record offset attribute setter.
     */
    public function tr(string $record_offset_attribute_name): self
    {
        if ($record_offset_attribute_name === $this->offset_attr) {
            return $this;
        }

        if (!self::filterAttributeNme($record_offset_attribute_name)) {
            throw new DOMException('The submitted attribute name `'.$record_offset_attribute_name.'` is not valid.');
        }

        $clone = clone $this;
        $clone->offset_attr = $record_offset_attribute_name;

        return $clone;
    }

    /**
     * HTML td field name attribute setter.
     */
    public function td(string $fieldname_attribute_name): self
    {
        if ($fieldname_attribute_name === $this->column_attr) {
            return $this;
        }

        if (!self::filterAttributeNme($fieldname_attribute_name)) {
            throw new DOMException('The submitted attribute name `'.$fieldname_attribute_name.'` is not valid.');
        }

        $clone = clone $this;
        $clone->column_attr = $fieldname_attribute_name;

        return $clone;
    }

    private static function filterAttributeNme(string $attribute_name): bool
    {
        try {
            $document = self::supportsModernDom() ? XmlDocument::createEmpty() : new DOMDocument('1.0');
            $div = $document->createElement('div');
            $div->setAttribute($attribute_name, 'foo');

            return true;
        } catch (DOMException) {
            return false;
        }
    }

    /**
     * Set a callback to format each item before json encode.
     *
     * @param ?callable(array, array-key): array $formatter
     */
    public function formatter(?callable $formatter): self
    {
        $clone = clone $this;
        $clone->formatter = ($formatter instanceof Closure || null === $formatter) ? $formatter : $formatter(...);

        return $clone;
    }

    /**
     * Apply the callback if the given "condition" is (or resolves to) true.
     *
     * @param (callable($this): bool)|bool $condition
     * @param callable($this): (self|null) $onSuccess
     * @param ?callable($this): (self|null) $onFail
     */
    public function when(callable|bool $condition, callable $onSuccess, ?callable $onFail = null): self
    {
        if (!is_bool($condition)) {
            $condition = $condition($this);
        }

        return match (true) {
            $condition => $onSuccess($this),
            null !== $onFail => $onFail($this),
            default => $this,
        } ?? $this;
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @see XMLConverter::__construct()
     * @deprecated Since version 9.22.0
     * @codeCoverageIgnore
     *
     * Returns an new instance.
     */
    #[Deprecated(message:'use League\Csv\HTMLConverter::__construct()', since:'league/csv:9.22.0')]
    public static function create(): self
    {
        return new self();
    }
}
