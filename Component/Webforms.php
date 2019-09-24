<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Acl\Builder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use VladimirPopov\WebForms\Model\FormFactory;

/**
 * Class Webforms
 * Process Webforms
 *
 * @package CtiDigital\Configurator\Model\Component
 */
class Webforms extends YamlComponentAbstract
{
    protected $alias = 'webforms';
    protected $name = 'Webforms';
    protected $description = 'Component to create webforms.';
    protected $requiredFields = ['source'];
    protected $defaultValues = ['is_active' => 1];

    /** @var FormFactory */
    protected $formFactory;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * Pages constructor.
     *
     * @param LoggerInterface $log
     * @param ObjectManagerInterface $objectManager
     * @param FormFactory $formFactory
     * @param RulesFactory $rulesFactory
     * @param CollectionFactory $rulesCollectionFactory
     * @param Builder $aclBuilder
     * @param Session $authSession
     */
    public function __construct(
        LoggerInterface $log,
        ObjectManagerInterface $objectManager,
        FormFactory $formFactory
    ) {
        $this->formFactory = $formFactory;
        parent::__construct($log, $objectManager);
    }

    /**
     * Loop through the data array and process page data
     *
     * @param $data
     * @return void
     */
    public function processData($data = null)
    {
        try {
            foreach ($data as $identifier) {
                $this->processForm($identifier);
            }
        } catch (ComponentException $e) {
            $this->log->logError($e->getMessage());
        }
    }

    /**
     * Process form data for import
     * @param $data
     */
    protected function processForm($data)
    {
        $model = $this->formFactory->create();
        foreach ($data as $formData) {
            $this->checkRequiredFields($formData);
            $this->setDefaultFields($formData);
            try {
                $importData = file_get_contents($formData['source']);
                $parse = $model->parseJson($importData);

                if (empty($parse['errors'])) {
                    $model->import($importData);
                    if ($model->getId()) {
                        $model->setIsActive($formData['is_active']);
                        $model->save();
                        $this->log->logComment(__('Form "%1" successfully imported.', $model->getName()));
                    }
                } else {
                    foreach ($parse['errors'] as $error) {
                        $this->log->logError($error);
                    }
                }
            } catch (\Exception $e) {
                $this->log->logError($e->getMessage());
            }
        }
    }

    /**
     * Check the required fields are set
     * @param $formData
     * @throws ComponentException
     */
    protected function checkRequiredFields($formData)
    {
        foreach ($this->requiredFields as $key) {
            if (!array_key_exists($key, $formData)) {
                throw new ComponentException('Required Data Missing ' . $key);
            }
        }
    }

    /**
     * Add default page data if fields not set
     * @param $formData
     */
    protected function setDefaultFields(&$formData)
    {
        foreach ($this->defaultValues as $key => $value) {
            if (!array_key_exists($key, $formData)) {
                $pageData[$key] = $value;
            }
        }
    }
}
