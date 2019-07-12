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

use DOMDocument;
use DOMElement;
use DOMException;
use Traversable;
use function preg_match;

/**
 * Converts tabular data into an HTML Table string.
 */
class HTMLConverter
{
    /**
     * table class attribute value.
     *
     * @var string
     */
    protected $class_name = 'table-csv-data';

    /**
     * table id attribute value.
     *
     * @var string
     */
    protected $id_value = '';

    /**
     * @var XMLConverter
     */
    protected $xml_converter;

    /**
     * New Instance.
     */
    public function __construct()
    {
        $this->xml_converter = (new XMLConverter())
            ->rootElement('table')
            ->recordElement('tr')
            ->fieldElement('td')
        ;
    }

    /**
     * Convert an Record collection into a DOMDocument.
     *
     * @param array|Traversable $records       The tabular data collection
     * @param string[]          $header_record An optional array of headers to output to the table using `<thead>` and `<th>` elements
     * @param string[]          $footer_record An optional array of footers to output to the table using `<tfoot>` and `<th>` elements
     */
    public function convert($records, array $header_record = [], array $footer_record = []): string
    {
        if ([] === $header_record && [] === $footer_record) {
            /** @var DOMDocument $doc */
            $doc = $this->xml_converter->convert($records);

            /** @var DOMElement $table */
            $table = $doc->getElementsByTagName('table')->item(0);
            $this->styleTableElement($table);

            return $doc->saveHTML($table);
        };

        $doc = new DOMDocument('1.0');
        $table = $doc->createElement('table');
        $this->styleTableElement($table);
        $this->appendTableHeader('thead', $header_record, $table);
        $this->appendTableHeader('tfoot', $footer_record, $table);
        $tbody = $this->xml_converter->rootElement('tbody')->import($records, $doc);
        $table->appendChild($tbody);
        $doc->appendChild($table);

        return $doc->saveHTML($table);
    }

    /**
     * HTML table class name setter.
     *
     * @throws DOMException if the id_value contains any type of whitespace
     */
    public function table(string $class_name, string $id_value = ''): self
    {
        if (1 === preg_match(",\s,", $id_value)) {
            throw new DOMException("the id attribute's value must not contain whitespace (spaces, tabs etc.)");
        }
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
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->recordElement('tr', $record_offset_attribute_name);

        return $clone;
    }

    /**
     * HTML td field name attribute setter.
     */
    public function td(string $fieldname_attribute_name): self
    {
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->fieldElement('td', $fieldname_attribute_name);

        return $clone;
    }

    /**
     * Create a DOMElement representing a single record of data.
     */
    private function appendTableHeader(string $node_name, array $record, DOMElement $table)
    {
        if ([] === $record) {
            return;
        }

        $node = (new XMLConverter())
            ->rootElement($node_name)
            ->recordElement('tr')
            ->fieldElement('th')
            ->import([$record], $table->ownerDocument)
        ;

        /** @var DOMElement $element */
        foreach ($node->getElementsByTagName('th') as $element) {
            $element->setAttribute('scope', 'col');
        }

        $table->appendChild($node);
    }

    /**
     * Style the table dom element.
     */
    private function styleTableElement(DOMElement $table_element)
    {
        $table_element->setAttribute('class', $this->class_name);
        $table_element->setAttribute('id', $this->id_value);
    }
}
