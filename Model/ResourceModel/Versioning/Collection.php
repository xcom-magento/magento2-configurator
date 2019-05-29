<?php
namespace CtiDigital\Configurator\Model\ResourceModel\Versioning;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'version';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('CtiDigital\Configurator\Model\Versioning', 'CtiDigital\Configurator\Model\ResourceModel\Versioning');
    }

}