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

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMException;

/**
 * Converts tabular data into a DOMDocument object.
 */
class XMLConverter
{
    /**
     * XML Root name.
     *
     * @var string
     */
    protected $root_name = 'csv';

    /**
     * XML Node name.
     *
     * @var string
     */
    protected $record_name = 'row';

    /**
     * XML Item name.
     *
     * @var string
     */
    protected $field_name = 'cell';

    /**
     * XML column attribute name.
     *
     * @var string
     */
    protected $column_attr = '';

    /**
     * XML offset attribute name.
     *
     * @var string
     */
    protected $offset_attr = '';

    /**
     * Conversion method list.
     *
     * @var array
     */
    protected $encoder = [
        'field' => [
            true => 'fieldToElementWithAttribute',
            false => 'fieldToElement',
        ],
        'record' => [
            true => 'recordToElementWithAttribute',
            false => 'recordToElement',
        ],
    ];

    /**
     * Convert a Record collection into a DOMDocument.
     */
    public function convert(iterable $records): DOMDocument
    {
        $doc = new DOMDocument('1.0');
        $node = $this->import($records, $doc);
        $doc->appendChild($node);

        return $doc;
    }

    /**
     * Create a new DOMElement related to the given DOMDocument.
     *
     * **DOES NOT** attach to the DOMDocument
     */
    public function import(iterable $records, DOMDocument $doc): DOMElement
    {
        $field_encoder = $this->encoder['field']['' !== $this->column_attr];
        $record_encoder = $this->encoder['record']['' !== $this->offset_attr];
        $root = $doc->createElement($this->root_name);
        foreach ($records as $offset => $record) {
            $node = $this->$record_encoder($doc, $record, $field_encoder, $offset);
            $root->appendChild($node);
        }

        return $root;
    }

    /**
     * Convert a CSV record into a DOMElement and
     * adds its offset as DOMElement attribute.
     */
    protected function recordToElementWithAttribute(
        DOMDocument $doc,
        array $record,
        string $field_encoder,
        int $offset
    ): DOMElement {
        $node = $this->recordToElement($doc, $record, $field_encoder);
        $node->setAttribute($this->offset_attr, (string) $offset);

        return $node;
    }

    /**
     * Convert a CSV record into a DOMElement.
     */
    protected function recordToElement(DOMDocument $doc, array $record, string $field_encoder): DOMElement
    {
        $node = $doc->createElement($this->record_name);
        foreach ($record as $node_name => $value) {
            $item = $this->$field_encoder($doc, (string) $value, $node_name);
            $node->appendChild($item);
        }

        return $node;
    }

    /**
     * Convert Cell to Item.
     *
     * Convert the CSV item into a DOMElement and adds the item offset
     * as attribute to the returned DOMElement
     *
     * @param int|string $node_name
     */
    protected function fieldToElementWithAttribute(DOMDocument $doc, string $value, $node_name): DOMElement
    {
        $item = $this->fieldToElement($doc, $value);
        $item->setAttribute($this->column_attr, (string) $node_name);

        return $item;
    }

    /**
     * Convert Cell to Item.
     *
     * @param string $value Record item value
     */
    protected function fieldToElement(DOMDocument $doc, string $value): DOMElement
    {
        $item = $doc->createElement($this->field_name);
        $item->appendChild($doc->createTextNode($value));

        return $item;
    }

    /**
     * XML root element setter.
     */
    public function rootElement(string $node_name): self
    {
        $clone = clone $this;
        $clone->root_name = $this->filterElementName($node_name);

        return $clone;
    }

    /**
     * Filter XML element name.
     *
     * @throws DOMException If the Element name is invalid
     */
    protected function filterElementName(string $value): string
    {
        return (new DOMElement($value))->tagName;
    }

    /**
     * XML Record element setter.
     */
    public function recordElement(string $node_name, string $record_offset_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->record_name = $this->filterElementName($node_name);
        $clone->offset_attr = $this->filterAttributeName($record_offset_attribute_name);

        return $clone;
    }

    /**
     * Filter XML attribute name.
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
     */
    public function fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->field_name = $this->filterElementName($node_name);
        $clone->column_attr = $this->filterAttributeName($fieldname_attribute_name);

        return $clone;
    }
}
