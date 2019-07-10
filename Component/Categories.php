<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Framework\Webapi\Exception;
use Symfony\Component\Yaml\Yaml;
use \Magento\Framework\App\Bootstrap;

class Categories extends YamlComponentAbstract
{
    protected $alias = 'categories';
    protected $name = 'Categories';
    protected $description = 'Component to import categories.';
    protected $groupFactory;
    protected $category;
    private $mainAttributes = [
        'name',
        'is_active',
        'position',
        'include_in_menu',
        'description'
    ];

    protected $storeManager;
    protected $categoryFactory;

    public function __construct(
        \CtiDigital\Configurator\Api\LoggerInterface $log,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\CategoryFactory $category,
        \Magento\Store\Model\GroupFactory $groupFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $dirList,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory
    )
    {
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->category = $category;
        $this->groupFactory = $groupFactory;
        $this->dirList = $dirList;
        parent::__construct($log, $objectManager);
    }

    public function processData($data = null)
    {
        if (isset($data['categories'])) {
            foreach ($data['categories'] as $store) {
                try {
                    if (isset($store['store_group'])) {
                        $catName = $store['store_group'];
                        if (isset($store['new_root']) && $store['new_root'] === true) {
                            $cleanurl = trim(preg_replace('/ +/', '-', preg_replace('/[^A-Za-z0-9 ]/', '', urldecode(html_entity_decode(strip_tags(strtolower($store['store_group'])))))));
                            $storeId = $store['root_store_id'];

                            $bootstrap = Bootstrap::create(BP, $_SERVER);
                            $objectManager = $bootstrap->getObjectManager();
                            $url = \Magento\Framework\App\ObjectManager::getInstance();
                            $storeManager = $url->get('\Magento\Store\Model\StoreManagerInterface');
                            $mediaurl = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
                            $state = $objectManager->get('\Magento\Framework\App\State');
                            $state->setAreaCode('frontend');

                            $rootNodeId = 1; //set it as 1.
                            $rootCat = $objectManager->get('Magento\Catalog\Model\Category');
                            $cat_info = $rootCat->load($rootNodeId);

                            $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');
                            $categoryTmp = $categoryFactory->create();
                            $categoryTmp->setName($catName);
                            $categoryTmp->setIsActive(true);
                            $categoryTmp->setUrlKey($cleanurl);
                            $categoryTmp->setParentId($rootCat->getId());
                            $categoryTmp->setStoreId($storeId);
                            $categoryTmp->setPath($rootCat->getPath());
                            $categoryTmp->save();
                        }

                        if (!isset($store['new_root']) || $store['new_root'] === false) {
                            $category = $this->getDefaultCategory($catName);
                            if ($category === false) {
                                throw new ComponentException(
                                    sprintf('No default category was found for the store group "%s"', $store['store_group'])
                                );
                            }

                            if (isset($store['categories'])) {
                                $this->log->logInfo(
                                    sprintf('Updating categories for "%s"', $store['store_group'])
                                );
                                $this->createOrUpdateCategory($category, $store['categories']);
                            }
                        }
                    }
                } catch (ComponentException $e) {
                    $this->log->logError($e->getMessage());
                }
            }
        }
    }

    /**
     * Gets the default category for the store group
     *
     * @param null $store
     * @return \Magento\Catalog\Model\Category|bool
     */
    public function getDefaultCategory($store = null)
    {
        $groupCollection = $this->groupFactory->create()->getCollection()
            ->addFieldToFilter('name', $store);
        if ($groupCollection->getSize() === 1) {
            /**
             * @var $group \Magento\Store\Model\Group
             */
            $group = $groupCollection->getFirstItem();
            $category = $this->category->create()->load($group->getRootCategoryId());
            return $category;
        }
        if ($groupCollection->getSize() > 1) {
            throw new ComponentException(
                sprintf('Multiple store groups were found with the name "%s"', $store)
            );
        }
        if ($groupCollection->getSize() === 0) {
            throw new ComponentException(
                sprintf('No store groups were found with the name "%s"', $store)
            );
        }
        return false;
    }

    /**
     * Creates/updates categories with the values in the YAML
     *
     * @param array $categories
     * @param \Magento\Catalog\Model\Category $parentCategory
     * @SuppressWarnings(PHPMD)
     */
    public function createOrUpdateCategory(
        \Magento\Catalog\Model\Category $parentCategory,
        $categories = array()
    )
    {
        foreach ($categories as $categoryValues) {
            // Load the category using its name and parent category
            /**
             * @var $category \Magento\Catalog\Model\Category
             */
            $category = $this->category->create()->getCollection()
                ->addFieldToFilter('name', $categoryValues['name'])
                ->addFieldToFilter('parent_id', $parentCategory->getId())
                ->setPageSize(1)
                ->getFirstItem();

            foreach ($categoryValues as $attribute => $value) {
                switch ($attribute) {
                    case in_array($attribute, $this->mainAttributes):
                        $category->setData($attribute, $value);
                        break;
                    case 'category':
                        break;
                    case 'image':
                        $img = basename($value);
                        $path = parse_url($value);
                        $catMediaDir = $this->dirList->getPath('media') . '/' . 'catalog' . '/' . 'category' . '/';

                        if (!array_key_exists('host', $path)) {
                            $value = BP . '/' . trim($value, '/');
                        }

                        if (!@copy($value, $catMediaDir . $img)) {
                            $this->log->logError('Failed to find image: ' . $value, 1);
                            break;
                        }

                        $category->setImage($img);
                        break;
                    default:
                        $category->setCustomAttribute($attribute, $value);
                }
            }

            // Set the category to be active
            if (!(isset($categoryValues['is_active']))) {
                $category->setIsActive(true);
            }

            // Get the path. If the category exists, then append the '/' to the end
            $path = $parentCategory->getPath();
            if ($category->getId()) {
                $path = $path . '/';
            }
            $category->setAttributeSetId($category->getResource()->getEntityType()->getDefaultAttributeSetId());
            $category->setPath($path);
            $category->setParentId($parentCategory->getId());
            $category->save();

            $this->log->logInfo(
                sprintf('Updated category %s', $category->getName()),
                ($category->getLevel() - 1)
            );

            if (isset($categoryValues['categories'])) {
                $this->createOrUpdateCategory($category, $categoryValues['categories']);
            }
        }
    }
}
