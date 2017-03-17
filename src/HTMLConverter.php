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
     * table class name
     *
     * @var string
     */
    protected $class_name = 'table-csv-data';

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
    public function className(string $class_name): self
    {
        $clone = clone $this;
        $clone->class_name = $class_name;

        return $clone;
    }

    /**
     * HTML td field name attribute setter
     *
     * @param string $attribute_name
     *
     * @return self
     */
    public function fieldAttributeName(string $attribute_name): self
    {
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->fieldElement('td', $attribute_name);

        return $clone;
    }

    /**
     * HTML tr record offset attribute setter
     *
     * @param string $attribute_name
     *
     * @return self
     */
    public function recordOffsetAttributeName(string $attribute_name): self
    {
        $clone = clone $this;
        $clone->xml_converter = $this->xml_converter->recordElement('tr', $attribute_name);

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
        if ('' !== $this->class_name) {
            $doc->documentElement->setAttribute('class', $this->class_name);
        }

        return $doc->saveHTML($doc->documentElement);
    }
}
