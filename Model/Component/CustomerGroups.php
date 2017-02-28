<?php

namespace CtiDigital\Configurator\Model\Component;

use CtiDigital\Configurator\Model\Exception\ComponentException;
use Symfony\Component\Yaml\Yaml;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File;

class CustomerGroups extends ComponentAbstract
{
    const TYPE_CSV = 'csv';
    const TYPE_YAML = 'yaml';

    protected $alias = 'groups';
    protected $name = 'Customer Groups';
    protected $description = 'Component to import customer groups using a CSV file.';
    protected $type;
    protected $storeManager;
    protected $groupFactory;

    public function __construct(
        \CtiDigital\Configurator\Model\LoggingInterface $log,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Customer\Model\GroupFactory $groupFactory
    ) {
        parent::__construct($log, $objectManager);
        $this->groupFactory = $groupFactory;
    }

    protected function canParseAndProcess()
    {
        $path = BP . '/' . $this->source;
        if (!file_exists($path)) {
            throw new ComponentException(
                sprintf("Could not find file in path %s", $path)
            );
        }
        return true;
    }

    protected function parseData($source = null)
    {
        try {
            $fileType = $this->getFileType($source);
            if ($fileType === self::TYPE_YAML) {
                $this->type = self::TYPE_YAML;
                $parser = new Yaml();
                return $parser->parse(file_get_contents($source));
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    protected function processData($data = null)
    {
        // Prepare the data
        $groupsArray = array();

        if (array_key_exists('customer_groups', $data)) {
            $groupsArray = $data['customer_groups'];
        
            foreach ($groupsArray as $groupArray) {
                try {
                    $group = $this->groupFactory->create();
                    $group->setData($groupArray);
                    $group->save();
                    $this->log->logInfo($group->getCode());
                } catch (\Exception $e) {
                    $this->log->logError($e->getMessage() . ' (' . $groupArray['code'] . ')');
                }
            }
        }
        
    }

    /**
     * Gets the file extension
     *
     * @param null $source
     * @return mixed
     */
    public function getFileType ($source = null)
    {
        // Get the file extension so we know how to load the file
        $sourceFileInfo = pathinfo($source);
        if (!isset($sourceFileInfo['extension'])) {
            throw new ComponentException(
                sprintf('Could not find a valid extension for the source file.')
            );
        }
        $fileType = $sourceFileInfo['extension'];
        return $fileType;
    }

    /**
     * Gets the first row of the CSV file as these should be the attribute keys
     *
     * @param null $data
     * @return array
     */
    public function getAttributesFromCsv ($data = null)
    {
        $attributes = array();
        foreach ($data[0] as $attributeCode) {
            $attributes[] = $attributeCode;
        }
        return $attributes;
    }
}
