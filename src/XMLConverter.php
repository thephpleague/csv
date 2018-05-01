<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.1.4
* @package League.csv
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
use Traversable;
use TypeError;

/**
 * A class to convert tabular data into a DOMDOcument object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class XMLConverter
{
    /**
     * XML Root name
     *
     * @var string
     */
    protected $root_name = 'csv';

    /**
     * XML Node name
     *
     * @var string
     */
    protected $record_name = 'row';

    /**
     * XML Item name
     *
     * @var string
     */
    protected $field_name = 'cell';

    /**
     * XML column attribute name
     *
     * @var string
     */
    protected $column_attr = '';

    /**
     * XML offset attribute name
     *
     * @var string
     */
    protected $offset_attr = '';

    /**
     * Conversion method list
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
     * Convert an Record collection into a DOMDocument
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return DOMDocument
     */
    public function convert($records): DOMDocument
    {
        if (!\is_iterable($records)) {
            throw new TypeError(sprintf('%s() expects argument passed to be iterable, %s given', __METHOD__, gettype($records)));
        }

        $field_encoder = $this->encoder['field']['' !== $this->column_attr];
        $record_encoder = $this->encoder['record']['' !== $this->offset_attr];
        $doc = new DOMDocument('1.0');
        $root = $doc->createElement($this->root_name);
        foreach ($records as $offset => $record) {
            $node = $this->$record_encoder($doc, $record, $field_encoder, $offset);
            $root->appendChild($node);
        }
        $doc->appendChild($root);

        return $doc;
    }

    /**
     * Convert a CSV record into a DOMElement and
     * adds its offset as DOMElement attribute
     *
     * @param DOMDocument $doc
     * @param array       $record        CSV record
     * @param string      $field_encoder CSV Cell encoder method name
     * @param int         $offset        CSV record offset
     *
     * @return DOMElement
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
     * Convert a CSV record into a DOMElement
     *
     * @param DOMDocument $doc
     * @param array       $record        CSV record
     * @param string      $field_encoder CSV Cell encoder method name
     *
     * @return DOMElement
     */
    protected function recordToElement(DOMDocument $doc, array $record, string $field_encoder): DOMElement
    {
        $node = $doc->createElement($this->record_name);
        foreach ($record as $node_name => $value) {
            $item = $this->$field_encoder($doc, $value, $node_name);
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
     * @param DOMDocument $doc
     * @param string      $value     Record item value
     * @param int|string  $node_name Record item offset
     *
     * @return DOMElement
     */
    protected function fieldToElementWithAttribute(DOMDocument $doc, string $value, $node_name): DOMElement
    {
        $item = $this->fieldToElement($doc, $value);
        $item->setAttribute($this->column_attr, (string) $node_name);

        return $item;
    }

    /**
     * Convert Cell to Item
     *
     * @param DOMDocument $doc
     * @param string      $value Record item value
     *
     * @return DOMElement
     */
    protected function fieldToElement(DOMDocument $doc, string $value): DOMElement
    {
        $item = $doc->createElement($this->field_name);
        $item->appendChild($doc->createTextNode($value));

        return $item;
    }

    /**
     * XML root element setter
     *
     * @param string $node_name
     *
     * @return self
     */
    public function rootElement(string $node_name): self
    {
        $clone = clone $this;
        $clone->root_name = $this->filterElementName($node_name);

        return $clone;
    }

    /**
     * Filter XML element name
     *
     * @param string $value Element name
     *
     * @throws DOMException If the Element name is invalid
     *
     * @return string
     */
    protected function filterElementName(string $value): string
    {
        return (new DOMElement($value))->tagName;
    }

    /**
     * XML Record element setter
     *
     * @param string $node_name
     * @param string $record_offset_attribute_name
     *
     * @return self
     */
    public function recordElement(string $node_name, string $record_offset_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->record_name = $this->filterElementName($node_name);
        $clone->offset_attr = $this->filterAttributeName($record_offset_attribute_name);

        return $clone;
    }

    /**
     * Filter XML attribute name
     *
     * @param string $value Element name
     *
     * @throws DOMException If the Element attribute name is invalid
     *
     * @return string
     */
    protected function filterAttributeName(string $value): string
    {
        if ('' === $value) {
            return $value;
        }

        return (new DOMAttr($value))->name;
    }

    /**
     * XML Field element setter
     *
     * @param string $node_name
     * @param string $fieldname_attribute_name
     *
     * @return self
     */
    public function fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
    {
        $clone = clone $this;
        $clone->field_name = $this->filterElementName($node_name);
        $clone->column_attr = $this->filterAttributeName($fieldname_attribute_name);

        return $clone;
    }
}
