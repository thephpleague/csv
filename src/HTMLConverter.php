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
     * @param array|Traversable $records      The tabular data collection
     * @param array             $headerRecord An optional array of headers to output to the table using `<thead>` and `<th>` elements
     * @param array             $footerRecord An optional array of footers to output to the table using `<tfoot>` and `<th>` elements
     *
     * @return string
     */
    public function convert($records, array $headerRecord = [], array $footerRecord = []): string
    {
        if ([] === $headerRecord && [] === $footerRecord) {
            /** @var DOMDocument $doc */
            $doc = $this->xml_converter->convert($records);

            /** @var DOMElement $table */
            $table = $doc->getElementsByTagName('table')->item(0);
            $this->styleTableElement($table);

            return $doc->saveHTML($table);
        };

        $doc = new DOMDocument('1.0');

        $tbody = $this->xml_converter->rootElement('tbody')->import($records, $doc);
        $table = $doc->createElement('table');
        $this->styleTableElement($table);
        if (!empty($headerRecord)) {
            $table->appendChild(
                $this->createRecordRow('thead', 'th', $headerRecord, $doc)
            );
        }
        $table->appendChild($tbody);
        if (!empty($footerRecord)) {
            $table->appendChild(
                $this->createRecordRow('tfoot', 'th', $footerRecord, $doc)
            );
        }

        $doc->appendChild($table);

        return $doc->saveHTML();
    }

    /**
     * HTML table class name setter.
     *
     * @throws DOMException if the id_value contains any type of whitespace
     */
    public function table(string $class_name, string $id_value = ''): self
    {
        if (preg_match(",\s,", $id_value)) {
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
     * Create a DOMElement representing a single record of data
     *
     * @param string $recordTagName
     * @param string $fieldTagName
     * @param array $record
     * @param DOMDocument $doc
     *
     * @return DOMElement
     */
    private function createRecordRow(string $recordTagName, string $fieldTagName, array $record, DOMDocument $doc) : DOMElement
    {
        $node = $this->xml_converter->rootElement($recordTagName)->fieldElement($fieldTagName)->import([$record], $doc);

        /** @var DOMElement $element */
        foreach ($node->getElementsByTagName($fieldTagName) as $element) {
            $element->setAttribute('scope', 'col');
        }

        return $node;
    }

    /**
     * Style the table dom element
     *
     * @param DOMElement $table_element
     */
    private function styleTableElement(DOMElement $table_element)
    {
        $table_element->setAttribute('class', $this->class_name);
        $table_element->setAttribute('id', $this->id_value);
    }
}
