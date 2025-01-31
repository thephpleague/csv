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
use DOMDocument;
use DOMElement;
use DOMException;
use Exception;
use RuntimeException;
use ValueError;

use function class_exists;
use function extension_loaded;
use function in_array;
use function strtolower;
use function strtoupper;

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
    /** @var class-string */
    protected string $xml_class = DomDocument::class;

    /**
     * @param class-string $xml_class
     *
     * @throws RuntimeException If the extension is not present
     * @throws ValueError If the XML class used is invalid
     */
    private static function newXmlDocument(string $xml_class = DOMDocument::class): DOMDocument|XMLDocument
    {
        return match (true) {
            !extension_loaded('dom') => throw new RuntimeException('The DOM extension is not loaded.'),
            !in_array($xml_class, [XMLDocument::class , DOMDocument::class], true) => throw new ValueError('The xml class is invalid.'),
            XMLDocument::class === $xml_class && class_exists(XMLDocument::class) => XMLDocument::createEmpty(),
            default => new DOMDocument(encoding: 'UTF-8'),
        };
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

    /**
     * @param class-string $xmlClass
     */
    public function xmlClass(string $xmlClass): self
    {
        if (!in_array($xmlClass, [XMLDocument::class , DOMDocument::class], true)) {
            throw new ValueError('The xml class is invalid.');
        }

        if ($this->xml_class === $xmlClass) {
            return $this;
        }

        $clone = clone $this;
        $clone->xml_class = $xmlClass;

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
     * Sends and makes the XML structure downloadable via HTTP.
     *.
     * Returns the number of characters read from the handle and passed through to the output.
     *
     * @throws Exception
     */
    public function download(iterable $records, ?string $filename = null, string $encoding = 'utf-8', bool $formatOutput = false): int|false
    {
        /** @var XMLDocument|DOMDocument $document */
        $document = $this->convert($records);

        if (null !== $filename) {
            HttpHeaders::forFileDownload($filename, 'application/xml; charset='.strtolower($encoding));
        }

        $document->formatOutput = $formatOutput;
        if ($document instanceof DOMDocument) {
            $document->encoding = strtoupper($encoding);

            return $document->save('php://output');
        }

        return $document->saveXmlFile('php://output');
    }

    /**
     * Converts a Record collection into a DOMDocument.
     */
    public function convert(iterable $records): DOMDocument|XMLDocument
    {
        $document = self::newXmlDocument($this->xml_class);
        $document->appendChild($this->import($records, $document));

        return $document;
    }

    /**
     * Creates a new DOMElement related to the given DOMDocument.
     *
     * **DOES NOT** attach to the DOMDocument
     */
    public function import(iterable $records, DOMDocument|XMLDocument $doc): DOMElement|Element
    {
        if (null !== $this->formatter) {
            $records = MapIterator::fromIterable($records, $this->formatter);
        }

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
    protected function recordToElement(DOMDocument|XMLDocument $document, array $record, int $offset): DOMElement|Element
    {
        $node = $document->createElement($this->record_name);
        foreach ($record as $node_name => $value) {
            $node->appendChild($this->fieldToElement($document, (string) $value, $node_name));
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
    protected function fieldToElement(DOMDocument|XMLDocument $document, string $value, int|string $node_name): DOMElement|Element
    {
        $item = $document->createElement($this->field_name);
        $item->appendChild($document->createTextNode($value));

        if ('' !== $this->column_attr) {
            $item->setAttribute($this->column_attr, (string) $node_name);
        }

        return $item;
    }

    /**
     * Filters XML element name.
     *
     * @throws DOMException If the Element name is invalid
     */
    protected function filterElementName(string $value): string
    {
        return self::newXmlDocument(XMLDocument::class)->createElement($value)->tagName;
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

        $element = self::newXmlDocument(XMLDocument::class)->createElement('foo');
        $element->setAttribute($value, 'foo');

        return $value;
    }
}
