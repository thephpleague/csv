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

use Traversable;

/**
 * A class to convert CSV records into a DOMDOcument object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class HTMLConverter implements Converter
{
    use ConverterTrait;

    /**
     * table class attribute value
     *
     * @var string
     */
    protected $class_name = 'table-csv-data';

    /**
     * table id attribute value
     *
     * @var string
     */
    protected $id_name = '';

    /**
     * @var XMLConverter
     */
    protected $xml_converter;

    /**
     * New Instance
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
     * HTML table class name setter
     *
     * @param string $class_name
     *
     * @return self
     */
    public function table(string $class_name, string $id_name = ''): self
    {
        $clone = clone $this;
        $clone->class_name = $class_name;
        $clone->class_name = $id_name;

        return $clone;
    }

    /**
     * HTML tr record offset attribute setter
     *
     * @param string $record_offset_attribute_name
     *
     * @return self
     */
    public function tr(string $record_offset_attribute_name): self
    {
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->recordElement('tr', $record_offset_attribute_name);

        return $clone;
    }

    /**
     * HTML td field name attribute setter
     *
     * @param string $fieldname_attribute_name
     *
     * @return self
     */
    public function td(string $fieldname_attribute_name): self
    {
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->fieldElement('td', $fieldname_attribute_name);

        return $clone;
    }

    /**
     * Convert an Record collection into a DOMDocument
     *
     * @param array|Traversable $records the CSV records collection
     *
     * @return string
     */
    public function convert($records)
    {
        $doc = $this->xml_converter->inputEncoding($this->input_encoding)->convert($records);
        $doc->documentElement->setAttribute('class', $this->class_name);
        $doc->documentElement->setAttribute('id', $this->id_name);

        return $doc->saveHTML($doc->documentElement);
    }
}
