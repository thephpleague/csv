<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use DOMDocument;
use DOMElement;
use Traversable;

/**
 * A class to convert CSV records into a DOMDOcument object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @internal used internally to returns the DOMDocument object representation of a CSV document
 *
 */
class XMLEncoder
{
    use ValidatorTrait;

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
    protected $item_name = 'cell';

    /**
     * XML column attribute name
     *
     * @var string
     */
    protected $column_attr = 'name';

    /**
     * XML offset attribute name
     *
     * @var string
     */
    protected $offset_attr = 'offset';

    /**
     * Tell whether to preserve item offset
     *
     * @var bool
     */
    protected $preserve_item_offset = false;

    /**
     * Tell whether to preserve record offset
     *
     * @var bool
     */
    protected $preserve_record_offset = false;

    /**
     * Conversion method list
     *
     * @var array
     */
    protected $encoder = [
        'item' => [
            true => 'itemToElementWithAttribute',
            false => 'itemToElement',
        ],
        'record' => [
            true => 'recordToElementWithAttribute',
            false => 'recordToElement',
        ],
    ];

    /**
     * XML Root name setter
     *
     * @param string $root_name
     *
     * @return self
     */
    public function rootName(string $root_name): self
    {
        if ($root_name === $this->root_name) {
            return $this;
        }

        $clone = clone $this;
        $clone->root_name = $root_name;

        return $clone;
    }

    /**
     * XML Record name setter
     *
     * @param string $record_name
     *
     * @return self
     */
    public function recordName(string $record_name): self
    {
        if ($record_name === $this->record_name) {
            return $this;
        }

        $clone = clone $this;
        $clone->record_name = $record_name;

        return $clone;
    }

    /**
     * XML Item name setter
     *
     * @param string $item_name
     *
     * @return self
     */
    public function itemName(string $item_name): self
    {
        if ($item_name === $this->item_name) {
            return $this;
        }

        $clone = clone $this;
        $clone->item_name = $item_name;

        return $clone;
    }

    /**
     * XML Column attribute name setter
     *
     * @param string $column_attr
     *
     * @return self
     */
    public function columnAttributeName(string $column_attr): self
    {
        if ($column_attr === $this->column_attr) {
            return $this;
        }

        $clone = clone $this;
        $clone->column_attr = $column_attr;

        return $clone;
    }

    /**
     * XML Offset attribute name setter
     *
     * @param string $offset_attr
     *
     * @return self
     */
    public function offsetAttributeName(string $offset_attr): self
    {
        if ($offset_attr === $this->offset_attr) {
            return $this;
        }

        $clone = clone $this;
        $clone->offset_attr = $offset_attr;

        return $clone;
    }

    /**
     * Whether we should preserve the CSV records keys.
     *
     * If set to true CSV document record keys will added to
     * the conversion output .
     *
     * @param bool $status
     *
     * @return self
     */
    public function preserveItemOffset(bool $status): self
    {
        if ($status === $this->preserve_item_offset) {
            return $this;
        }

        $clone = clone $this;
        $clone->preserve_item_offset = $status;

        return $clone;
    }

    /**
     * Whether we should preserve the CSV records keys.
     *
     * If set to true CSV document record keys will added to
     * the conversion output .
     *
     * @param bool $status
     *
     * @return self
     */
    public function preserveRecordOffset(bool $status): self
    {
        if ($status === $this->preserve_record_offset) {
            return $this;
        }

        $clone = clone $this;
        $clone->preserve_record_offset = $status;

        return $clone;
    }

    /**
     * Convert an Record collection into a DOMDocument
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return DOMDocument
     */
    public function encode($records): DOMDocument
    {
        $item_encoder = $this->encoder['item'][$this->preserve_item_offset];
        $record_encoder = $this->encoder['record'][$this->preserve_record_offset];
        $doc = new DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElement($this->root_name);
        foreach ($this->filterIterable($records, __METHOD__) as $offset => $record) {
            $node = $this->{$record_encoder}($doc, $record, $item_encoder, $offset);
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
     * @param array       $record       CSV record
     * @param string      $item_encoder CSV Cell encoder method name
     * @param int         $offset       CSV record offset
     *
     * @return DOMElement
     */
    protected function recordToElementWithAttribute(DOMDocument $doc, array $record, string $item_encoder, int $offset): DOMElement
    {
        $node = $this->recordToElement($doc, $record, $item_encoder);
        $node->setAttribute($this->offset_attr, (string) $offset);

        return $node;
    }

    /**
     * Convert a CSV record into a DOMElement
     *
     * @param DOMDocument $doc
     * @param array       $record       CSV record
     * @param string      $item_encoder CSV Cell encoder method name
     *
     * @return DOMElement
     */
    protected function recordToElement(DOMDocument $doc, array $record, string $item_encoder): DOMElement
    {
        $node = $doc->createElement($this->record_name);
        foreach ($record as $name => $value) {
            $item = $this->{$item_encoder}($doc, $value, $name);
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
     * @param string      $value Record item value
     * @param int|string  $name  Record item offset
     *
     * @return DOMElement
     */
    protected function itemToElementWithAttribute(DOMDocument $doc, string $value, $name): DOMElement
    {
        $item = $this->itemToElement($doc, $value);
        $item->setAttribute($this->column_attr, (string) $name);

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
    protected function itemToElement(DOMDocument $doc, string $value): DOMElement
    {
        $item = $doc->createElement($this->item_name);
        $item->appendChild($doc->createTextNode($value));

        return $item;
    }
}
