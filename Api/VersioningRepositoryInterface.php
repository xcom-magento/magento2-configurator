<?php

namespace CtiDigital\Configurator\Api;

use CtiDigital\Configurator\Api\Data\VersioningInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Interface VersioningRepositoryInterface
 */
interface VersioningRepositoryInterface
{
    /**
     * Save version
     *
     * @param  VersioningInterface $versioning
     * @return VersioningInterface
     * @throws LocalizedException
     */
    public function save(Data\VersioningInterface $versioning);

    /**
     * Retrieve versioning matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return VersioningInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria);
}
