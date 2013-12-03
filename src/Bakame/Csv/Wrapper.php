<?php

namespace Bakame\Csv;

use SplFileInfo;
use SplFileObject;
use SplTempFileObject;
use Traversable;
use InvalidArgumentException;
use RuntimeException;

class Wrapper
{
    /**
     * file access type supported
     *
     * @var [type]
     */
    private $mode_list = ['r', 'r+', 'w', 'w+', 'x', 'x+', 'a', 'a+', 'c', 'c+'];

    /**
     * the field delimiter (one character only)
     *
     * @var string
     */
    private $delimiter = ',';

    /**
     * the field enclosure character (one character only)
     *
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
     *
     * @param string $delimiter
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $delimeter is not a single character
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
     *
     * @param string $enclosure
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $enclosure is not a single character
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
     *
     * @param string $escape
     *
     * @return self
     *
     * @throws \InvalidArgumentException If $escape is not a single character
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
     * Load a CSV string
     *
     * @param string $str the csv content string
     *
     * @return \SplTempFileObject
     */
    public function loadString($str)
    {

        $file = new SplTempFileObject();
        $file->fwrite($str);
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $file;
    }

    /**
     * Load a CSV File
     *
     * @param string $str the file path
     *
     * @return \SplFileObject
     *
     * @throws \RuntimeException if the $file can not be instantiate
     */
    public function loadFile($path, $mode = 'r')
    {
        $this->filterMode($mode, ['w', 'a', 'x', 'c']);

        return $this->create($path, $mode);
    }

    /**
     * Return a new \SplFileObject
     *
     * @param string|SplFileInfo $path where to save the data
     * @param string             $mode specifies the type of access you require to the file
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If the $mode is invalid
     * @throws \InvalidArgumentException If the $file is not set
     */
    public function create($path, $mode)
    {
        $this->filterMode($mode);
        if ($path instanceof SplFileInfo) {
            $file = $path->openFile($mode);
        } elseif (is_string($path)) {
            $file = new SplFileObject($path, $mode);
        }
        if (! isset($file)) {
            throw new InvalidArgumentException('$path must be a SplFileInfo object or a valid file path.');
        }
        $file->setFlags(SplFileObject::READ_CSV);
        $file->setCsvControl($this->delimiter, $this->enclosure, $this->escape);

        return $file;
    }

    /**
     * validate the type of access you require for a given file
     *
     * @param string $mode    specifies the type of access you require to the file
     * @param array  $options non valid type of access
     */
    private function filterMode(&$mode, array $options = [])
    {
        $mode = strtolower($mode);
        $mode_list = array_diff($this->mode_list, $options);
        if (! in_array($mode, $mode_list)) {
            throw new InvalidArgumentException('$mode can not be equal to "'.$mode.'"');
        }
    }

    /**
     * Save the given data into a CSV
     *
     * @param array|Traversable  $data the data to be saved
     * @param string|SplFileInfo $path where to save the data
     * @param string             $mode specifies the type of access you require to the file
     *
     * @return \SplFileObject
     *
     * @throws \InvalidArgumentException If $data is not an array or a Traversable object
     * @throws \InvalidArgumentException If the $mode is invalid
     */
    public function save($data, $path, $mode = 'w')
    {
        if (! is_array($data) && ! $data instanceof Traversable) {
            throw new InvalidArgumentException('$data must be an Array or a Traversable object');
        }

        $this->filterMode($mode, ['r']);
        $file = $this->create($path, $mode);
        foreach ($data as $row) {
            if (is_string($row)) {
                $row = explode($this->delimiter, $row);
            }
            $row = (array) $row;
            array_walk($row, function (&$value) {
                $value = (string) $value;
                $value = trim($value);
            });
            $file->fputcsv($row);
        }

        return $file;
    }
}
