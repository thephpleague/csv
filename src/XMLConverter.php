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
use Dom\Element;
use Dom\XMLDocument;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMException;
use Exception;

use function class_exists;
use function extension_loaded;

/**
 * Converts tabular data into a DOMDocument object.
 */
class XMLConverter
{
    /** XML Root name. */
    protected string $root_name = 'csv';
    /** XML Node name. */
    protected string $record_name = 'row';
    /** XML Item name. */
    protected string $field_name = 'cell';
    /** XML column attribute name. */
    protected string $column_attr = '';
    /** XML offset attribute name. */
    protected string $offset_attr = '';
    /** @var ?Closure(array, array-key): array */
    protected ?Closure $formatter = null;

    private static function supportsModerDom(): bool
    {
        return extension_loaded('dom') && class_exists(XMLDocument::class);
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release.
     *
     * @deprecated since version 9.7.0
     * @see XMLConverter::create()
     */
    public function __construct()
    {
    }

    /**
     * Converts a Record collection into a DOMDocument.
     */
    public function convert(iterable $records): DOMDocument|XMLDocument
    {
        if (null !== $this->formatter) {
            $records = MapIterator::fromIterable($records, $this->formatter);
        }

        $doc = self::supportsModerDom() ? XMLDocument::createEmpty() : new DOMDocument(version:'1.0', encoding:'UTF-8');
        $node = $this->import($records, $doc);
        $doc->appendChild($node);

        return $doc;
    }

    /**
     * Sends and makes the XML structure downloadable via HTTP.
     *.
     * Returns the number of characters read from the handle and passed through to the output.
     *
     * @throws Exception
     */
    public function download(iterable $records, ?string $filename = null, string $encoding = 'utf-8', bool $formatOutput = false): int|false
    {
        $document = $this->convert($records);

        if (null !== $filename) {
            HttpHeaders::forFileDownload($filename, 'application/xml; charset='.strtolower($encoding));
        }

        if ($document instanceof DOMDocument) {
            return $document->save('php://output');
        }

        return $document->saveXmlFile('php://output');
    }

    /**
     * Creates a new DOMElement related to the given DOMDocument.
     *
     * **DOES NOT** attach to the DOMDocument
     */
    public function import(iterable $records, DOMDocument|XMLDocument $doc): DOMElement|Element
    {
        $root = $doc->createElement($this->root_name);
        foreach ($records as $offset => $record) {
            $root->appendChild($this->recordToElement($doc, $record, $offset));
        }

        return $root;
    }

    /**
     * Converts a CSV record into a DOMElement and
     * adds its offset as DOMElement attribute.
     */
    protected function recordToElement(DOMDocument|XMLDocument $doc, array $record, int $offset): DOMElement|Element
    {
        $node = $doc->createElement($this->record_name);
        foreach ($record as $node_name => $value) {
            $item = $this->fieldToElement($doc, (string) $value, $node_name);
            $node->appendChild($item);
        }

        if ('' !== $this->offset_attr) {
            $node->setAttribute($this->offset_attr, (string) $offset);
        }

        return $node;
    }

    /**
     * Converts Cell to Item.
     *
     * Converts the CSV item into a DOMElement and adds the item offset
     * as attribute to the returned DOMElement
     */
    protected function fieldToElement(DOMDocument|XMLDocument $doc, string $value, int|string $node_name): DOMElement|Element
    {
        $item = $doc->createElement($this->field_name);
        $item->appendChild($doc->createTextNode($value));

        if ('' !== $this->column_attr) {
            $item->setAttribute($this->column_attr, (string) $node_name);
        }

        return $item;
    }

    /**
     * XML root element setter.
     *
     * @throws DOMException
     */
    public function rootElement(string $node_name): self
    {
        $clone = clone $this;
        $clone->root_name = $this->filterElementName($node_name);

        return $clone;
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
     * Filters XML element name.
     *
     * @throws DOMException If the Element name is invalid
     */
    protected function filterElementName(string $value): string
    {
        return (new DOMElement($value))->tagName;
    }

    /**
     * XML Record element setter.
     *
     * @throws DOMException
     */
    public function recordElement(string $node_name, string $record_offset_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->record_name = $this->filterElementName($node_name);
        $clone->offset_attr = $this->filterAttributeName($record_offset_attribute_name);

        return $clone;
    }

    /**
     * Filters XML attribute name.
     *
     * @param string $value Element name
     *
     * @throws DOMException If the Element attribute name is invalid
     */
    protected function filterAttributeName(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        return (new DOMAttr($value))->name;
    }

    /**
     * XML Field element setter.
     *
     * @throws DOMException
     */
    public function fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->field_name = $this->filterElementName($node_name);
        $clone->column_attr = $this->filterAttributeName($fieldname_attribute_name);

        return $clone;
    }
}
