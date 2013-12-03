<?php

namespace Bakame\Csv;

use InvalidArgumentException;
use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use Traversable;

class Wrapper
{
    /**
     * the field delimiter (one character only)
     * @var string
     */
    private $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     * @var string
     */
    private $enclosure = '"';

    /**
     * the field escape character (one character only)
     * @var string
     */
    private $escape = '\\';

    /**
     * set the field delimeter
     * @param string $delimiter
     *
     * @return self
     */
    public function setDelimiter($delimiter = ',')
    {
        if (1 != mb_strlen($delimiter)) {
            throw new InvalidArgumentException('The delimiter must be a single character');
        }
        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * set the field enclosure
     * @param string $enclosure
     *
     * @return self
     */
    public function setEnclosure($enclosure = '"')
    {
        if (1 != mb_strlen($enclosure)) {
            throw new InvalidArgumentException('The enclosure must be a single character');
        }
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * set the field escape character
     * @param string $escape
     *
     * @return self
     */
    public function setEscape($escape = "\\")
    {
        if (1 != mb_strlen($escape)) {
            throw new InvalidArgumentException('The escape character must be a single character');
        }
        $this->escape = $escape;

        return $this;
    }

    /**
     * return the current field delimiter
     *
     * @return string
     */
    public function getDelimiter()
    {
        return $this->delimiter;
    }

    /**
     * return the current field enclosure
     *
     * @return string
     */
    public function getEnclosure()
    {
        return $this->enclosure;
    }

    /**
     * return the current field escape character
     *
     * @return string
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * The constructor
     *
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     */
    public function __construct($delimiter = ',', $enclosure = '"', $escape = "\\")
    {
        $this->setDelimiter($delimiter);
        $this->setEnclosure($enclosure);
        $this->setEscape($escape);
    }

    /**
     * Parse a CSV string
     * @param string $str the csv content string
     *
     * @return \SplTempFileObject
     */
    public function loadString($str)
    {

        $file = new SplTempFileObject();
        $file->fwrite($str);
        $file->setFlags(SplFileObject::READ_CSV|SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $file;
    }

    /**
     * Parse a CSV File
     * @param string $str the file path
     *
     * @return \SplFileObject
     */
    public function loadFile($str)
    {
        $file = new SplFileObject($str, 'r+');
        $file->setFlags(SplFileObject::READ_CSV|SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $file;
    }

    /**
     * Save the given data into a CSV
     * @param mixed $data (Can be an array OR an Traversable object)
     * @param mixed $path (Can be a file path OR an SplFileInfo object)
     *
     * @return \SplFileObject
     */
    public function save($data, $path)
    {
        if (! is_array($data) && ! $data instanceof Traversable) {
            throw new InvalidArgumentException('$data must be an Array or a Traversable instance');
        }
        $file = null;
        if ($path instanceof SplFileInfo) {
            $file = $path->openFile('w');
        } elseif (is_string($path)) {
            $file = new SplFileObject($path, 'w');
        }
        if (! $file instanceof SplFileObject) {
            throw new InvalidArgumentException('You must specify a SplFileInfo Object or a valid File Path');
        }
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        foreach ($data as $row) {
            if (is_string($row)) {
                $row = explode($this->delimiter, $row);
            }
            $row = (array) $row;
            array_walk($row, function (&$value) {
                $value = trim($value);
            });
            $file->fputcsv($row);
        }
        $file->setFlags(SplFileObject::READ_CSV|SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

        return $file;
    }
}
