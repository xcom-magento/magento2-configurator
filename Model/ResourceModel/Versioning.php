<?php
namespace CtiDigital\Configurator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Versioning extends AbstractDb
{
    protected $_useIsObjectNew = true;
    protected $_isPkAutoIncrement = false;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init("magento_configurator_versioning","version");
    }
}