<?php
namespace CtiDigital\Configurator\Model;

use CtiDigital\Configurator\Api\Data\VersioningInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class Versioning extends AbstractModel implements VersioningInterface, IdentityInterface
{
    const CACHE_TAG = 'magento_configurator_versioning';

    /**
     * {@inheritdoc}
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Versioning::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @inheritDoc
     */
    public function getVersion()
    {
        return $this->getData(self::VERSION);
    }

    /**
     * @inheritDoc
     */
    public function getUpdateTime()
    {
        return $this->getData(self::UPDATE_TIME);
    }

    /**
     * @inheritDoc
     */
    public function setVersion($version)
    {
        return $this->setData(self::VERSION, $version);
    }

    /**
     * @inheritDoc
     */
    public function setUpdateTime($updateTime)
    {
        return $this->setData(self::UPDATE_TIME, $updateTime);
    }

    /**
     * @inheritDoc
     */
    public function isObjectNew($flag = null)
    {
        return parent::isObjectNew($flag);
    }
}