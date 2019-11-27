<?php
namespace CtiDigital\Configurator\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\App\ProductMetadata;

/**
 * @codeCoverageIgnore
 */
class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * @var ProductMetadata
     */
    protected $_productMetadata;

    /**
     * UpgradeSchema constructor.
     * @param ProductMetadata $productMetadata
     */
    public function __construct(
        ProductMetadata $productMetadata
    )
    {
        $this->_productMetadata = $productMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (version_compare($context->getVersion(), '1.0.0', '<') && version_compare($this->_productMetadata->getVersion(), '2.3.0', '<')) {
            $this->addVersioningTable($installer);
        }

        $installer->endSetup();
    }

    /**
     * @param $setup
     */
    protected function addVersioningTable($setup)
    {
        $tableName = 'magento_configurator_versioning';
        if (!$setup->getConnection()->isTableExists($tableName)) {
            $table = $setup->getConnection()
                ->newTable($setup->getTable($tableName))
                ->addColumn(
                    'version',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    ['identity' => true, 'unsigned' => false, 'nullable' => false, 'primary' => true],
                    'Version'
                )
                ->addColumn(
                    'update_time',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
                    null,
                    ['nullable' => true, 'on_update' => false],
                    'Update time'
                )->setComment("Greeting Message table");
            $setup->getConnection()->createTable($table);
        }
    }
}