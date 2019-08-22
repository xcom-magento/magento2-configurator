<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Exception\ComponentException;
use CtiDigital\Configurator\Api\LoggerInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Pages
 * Process Magento CMS Pages
 *
 * @package CtiDigital\Configurator\Model\Component
 */
class Pages extends YamlComponentAbstract
{
    protected $alias = 'pages';
    protected $name = 'Pages';
    protected $description = 'Component to create/maintain pages.';
    protected $requiredFields = ['title'];
    protected $defaultValues = ['page_layout' => 'empty', 'is_active' => '1'];

    /** @var PageRepositoryInterface */
    protected $pageRepository;

    /** @var PageInterfaceFactory */
    protected $pageFactory;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * Pages constructor.
     *
     * @param LoggerInterface $log
     * @param ObjectManagerInterface $objectManager
     * @param PageRepositoryInterface $pageRepository
     * @param PageInterfaceFactory $pageFactory
     * @param StoreRepositoryInterface $storeRepository
     */
    public function __construct(
        LoggerInterface $log,
        ObjectManagerInterface $objectManager,
        PageRepositoryInterface $pageRepository,
        PageInterfaceFactory $pageFactory,
        StoreRepositoryInterface $storeRepository
    ) {
        $this->pageFactory = $pageFactory;
        $this->pageRepository = $pageRepository;
        $this->storeRepository = $storeRepository;

        parent::__construct($log, $objectManager);
    }


    /**
     * Loop through the data array and process page data
     *
     * @param $data
     * @return void
     */
    protected function processData($data = null)
    {
        try {
            foreach ($data as $identifier => $data) {
                $this->processPage($identifier, $data);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    /**
     * Create or update page data
     *
     * @param $identifier
     * @param $data
     * @SuppressWarnings(PHPMD)
     */
    protected function processPage($identifier, $data)
    {

        try {
            foreach ($data['page'] as $pageData) {
                if (isset($pageData['stores'])) {
                    foreach ($pageData['stores'] as $storeCode) {
                        $store = $this->storeRepository->get($storeCode);
                        $pageId = $this->pageFactory->create()->checkIdentifier($identifier, $store->getId());
                    }
                } else {
                    $pageId = $this->pageFactory->create()->checkIdentifier($identifier, 0);
                }

                /** @var \Magento\Cms\Api\Data\PageInterface $page */
                if ($pageId) {
                    $page = $this->pageRepository->getById($pageId);
                } else {
                    $page = $this->pageFactory->create();
                    $page->setIdentifier($identifier);
                }

                $this->checkRequiredFields($pageData);
                $this->setDefaultFields($pageData);

                // Loop through each attribute of the data array
                foreach ($pageData as $key => $value) {
                    // Check if content is from a file source
                    if ($key == "source") {
                        $key = 'content';
                        $value = file_get_contents(BP . '/' . $value);
                    }

                    // Skip stores
                    if ($key == "stores") {
                        continue;
                    }

                    // Log the old value if any
                    $this->log->logComment(sprintf(
                        "Checking page %s, key %s => %s",
                        $identifier . ' (' . $page->getId() . ')',
                        $key,
                        $page->getData($key)
                    ), 1);

                    // Check if there is a difference in value
                    if ($page->getData($key) != $value) {
                        $page->setData($key, $value);

                        $this->log->logInfo(sprintf(
                            "Set page %s, key %s => %s",
                            $identifier . ' (' . $page->getId() . ')',
                            $key,
                            $value
                        ), 1);
                    }
                }

                // Process stores
                $page->setStores([0]);
                if (isset($pageData['stores'])) {
                    $page->unsetData('store_id');
                    $page->unsetData('store_data');

                    $stores = array();
                    foreach ($pageData['stores'] as $code) {
                        $stores[] = $store = $this->storeRepository->get($code)->getId();
                    }

                    $page->setStores($stores);
                }

                //we only need to save if the model has changed
                if ($page->hasDataChanges()) {
                    $this->pageRepository->save($page);
                    $this->log->logInfo(sprintf(
                        "Save page %s",
                        $identifier . ' (' . $page->getId() . ')'
                    ));
                }
            }
        } catch (NoSuchEntityException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    /**
     * Check the required fields are set
     * @param $pageData
     * @throws ComponentException
     */
    protected function checkRequiredFields($pageData)
    {
        foreach ($this->requiredFields as $key) {
            if (!array_key_exists($key, $pageData)) {
                throw new ComponentException('Required Data Missing ' . $key);
            }
        }
    }

    /**
     * Add default page data if fields not set
     * @param $pageData
     */
    protected function setDefaultFields(&$pageData)
    {
        foreach ($this->defaultValues as $key => $value) {
            if (!array_key_exists($key, $pageData)) {
                $pageData[$key] = $value;
            }
        }
    }
}
