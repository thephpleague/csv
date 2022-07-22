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

namespace League\Csv\Doctrine;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use League\Csv\TabularDataReader;

final class RecordCollection extends AbstractLazyCollection implements Selectable
{
    public function __construct(
        private TabularDataReader $tabularDataReader
    ) {
    }

    /**
     * {@inheritDoc}
     */
    protected function doInitialize(): void
    {
        $this->collection = new ArrayCollection();
        foreach ($this->tabularDataReader as $offset => $record) {
            $this->collection->offsetSet($offset, $record);
        }

        unset($this->tabularDataReader);
    }

    /**
     * {@inheritDoc}
     */
    public function matching(Criteria $criteria): ArrayCollection
    {
        $this->initialize();

        /** @var ArrayCollection $collection */
        $collection = $this->collection;

        /** @var ArrayCollection $newCollection */
        $newCollection = $collection->matching($criteria);

        return $newCollection;
    }
}
