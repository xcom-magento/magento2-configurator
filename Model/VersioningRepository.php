<?php

namespace CtiDigital\Configurator\Model;

use CtiDigital\Configurator\Api\Data;
use CtiDigital\Configurator\Api\Data\VersioningInterface;
use CtiDigital\Configurator\Api\VersioningRepositoryInterface;
use CtiDigital\Configurator\Model\ResourceModel\Versioning as ResourceVersioning;
use CtiDigital\Configurator\Model\ResourceModel\Versioning\CollectionFactory as VersioningCollectionFactory;
use CtiDigital\Configurator\Api\Data\VersioningSearchResultsInterfaceFactory;
use CtiDigital\Configurator\Api\Data\VersioningSearchResultsInterface;
use Magento\Framework\Api\DataObjectHelper;
use CtiDigital\Configurator\Api\Data\VersioningInterfaceFactory;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Api\SortOrder;

class VersioningRepository implements VersioningRepositoryInterface
{
    /**
     * @var ResourceVersioning
     */
    private $resource;

    /**
     * @var VersioningFactory
     */
    private $versioningFactory;

    /**
     * @var VersioningCollectionFactory
     */
    private $versioningCollectionFactory;

    /**
     * @var VersioningSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var DataObjectProcessor
     */
    protected $dataObjectProcessor;

    /**
     * @var VersioningInterfaceFactory
     */
    private $dataVersioningFactory;

    /**
     * VersioningRepository constructor.
     * @param ResourceVersioning $resource
     * @param VersioningFactory $versioningFactory
     * @param VersioningInterfaceFactory $dataVersioningFactory
     * @param VersioningCollectionFactory $versioningCollectionFactory
     * @param VersioningSearchResultsInterfaceFactory $searchResultsFactory
     * @param DataObjectHelper $dataObjectHelper
     * @param DataObjectProcessor $dataObjectProcessor
     */
    public function __construct(
        ResourceVersioning $resource,
        VersioningFactory $versioningFactory,
        VersioningInterfaceFactory $dataVersioningFactory,
        VersioningCollectionFactory $versioningCollectionFactory,
        VersioningSearchResultsInterfaceFactory $searchResultsFactory,
        DataObjectHelper $dataObjectHelper,
        DataObjectProcessor $dataObjectProcessor
    ) {
        $this->resource = $resource;
        $this->versioningFactory = $versioningFactory;
        $this->versioningCollectionFactory = $versioningCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->dataVersioningFactory = $dataVersioningFactory;
        $this->dataObjectProcessor = $dataObjectProcessor;
    }

    /**
     * Save data
     *
     * @param VersioningInterface $versioning
     * @return VersioningInterface
     * @throws CouldNotSaveException
     */
    public function save(Data\VersioningInterface $versioning)
    {
        try {
            $this->resource->save($versioning);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }
        return $versioning;
    }

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return VersioningSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria)
    {
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $collection = $this->versioningCollectionFactory->create();
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $condition = $filter->getConditionType() ?: 'eq';
                $collection->addFieldToFilter($filter->getField(), [$condition => $filter->getValue()]);
            }
        }
        $searchResults->setTotalCount($collection->getSize());
        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders) {
            foreach ($sortOrders as $sortOrder) {
                $collection->addOrder(
                    $sortOrder->getField(),
                    ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
                );
            }
        }
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $versioningArray = [];
        /** @var Versioning $versioningModel */
        foreach ($collection as $versioningModel) {
            $dataVersioning = $this->dataVersioningFactory->create();

            $this->dataObjectHelper->populateWithArray(
                $dataVersioning,
                $versioningModel->getData(),
                'CtiDigital\Configurator\Api\Data\VersioningInterface'
            );
            $versioningArray[] = $this->dataObjectProcessor->buildOutputDataArray(
                $dataVersioning,
                'CtiDigital\Configurator\Api\Data\VersioningInterface'
            );
        }
        $searchResults->setItems($versioningArray);
        return $searchResults;
    }

}