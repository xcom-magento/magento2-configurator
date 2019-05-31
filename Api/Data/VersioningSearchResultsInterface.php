<?php

namespace CtiDigital\Configurator\Api\Data;

use CtiDigital\Configurator\Api\Data\VersioningInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface VersioningSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get versioning list.
     *
     * @return VersioningInterface[]
     */
    public function getItems();

    /**
     * Set versioning list.
     *
     * @param VersioningInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}