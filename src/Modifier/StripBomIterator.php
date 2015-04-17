<?php

namespace League\Csv\Modifier;

use Traversable;

/**
 * Iterator which strips the specified BOM character from the beginning of the first row, first element.
 */
class StripBomIterator extends \IteratorIterator
{
    /**
     * @var string
     */
    private $bom;

    /**
     * @param Traversable $iterator
     * @param string $bom
     */
    public function __construct(Traversable $iterator, $bom)
    {
        parent::__construct($iterator);
        $this->bom = $bom;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        $row = parent::current();

        if ($this->key() !== 0) {
            return $row;
        }

        $row[0] = ltrim($row[0], $this->bom);

        return $row;
    }
}
