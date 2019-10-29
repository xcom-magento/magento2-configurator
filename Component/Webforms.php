<?php

namespace CtiDigital\Configurator\Component;

use CtiDigital\Configurator\Api\LoggerInterface;
use CtiDigital\Configurator\Exception\ComponentException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManager;
use VladimirPopov\WebForms\Helper\Data;
use VladimirPopov\WebForms\Model\FieldFactory;
use VladimirPopov\WebForms\Model\FieldsetFactory;
use VladimirPopov\WebForms\Model\Form;
use VladimirPopov\WebForms\Model\FormFactory;
use VladimirPopov\WebForms\Model\LogicFactory;
use VladimirPopov\WebForms\Model\ResourceModel\Form\CollectionFactory;

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
    protected $defaultValues = ['is_active' => 1, 'overwrite_existing' => 1];

    /** @var FormFactory */
    protected $formFactory;
    protected $collectionFactory;

    /**
     * @var FieldFactory
     */
    protected $_fieldFactory;
    protected $_webformsHelper;
    protected $_storeManager;
    protected $_fieldsetFactory;
    protected $_logicFactory;

    /**
     * Pages constructor.
     *
     * @param LoggerInterface $log
     * @param ObjectManagerInterface $objectManager
     * @param FormFactory $formFactory
     * @param CollectionFactory $collection
     * @param FieldFactory $fieldFactory
     * @param Data $helper
     * @param StoreManager $storeManager
     * @param FieldsetFactory $fieldsetFactory
     * @param Form $form
     */
    public function __construct(
        LoggerInterface $log,
        ObjectManagerInterface $objectManager,
        FormFactory $formFactory,
        CollectionFactory $collection,
        FieldFactory $fieldFactory,
        Data $helper,
        StoreManager $storeManager,
        FieldsetFactory $fieldsetFactory,
        LogicFactory $logicFactory
    ) {
        $this->formFactory = $formFactory;
        $this->collectionFactory = $collection;
        $this->_fieldFactory = $fieldFactory;
        $this->_webformsHelper = $helper;
        $this->_storeManager = $storeManager;
        $this->_fieldsetFactory = $fieldsetFactory;
        $this->_logicFactory = $logicFactory;
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
        $unsetAfter = [
            'fields',
            'fieldsets',
            'logic',
            'store_data'
        ];
        foreach ($data as $formData) {
            $this->checkRequiredFields($formData);
            $this->setDefaultFields($formData);
            try {
                $importData = file_get_contents($formData['source']);
                $parse = $model->parseJson($importData);

                if (empty($parse['errors'])) {
                    // multiple forms with the same name will all be updated
                    $dataArray = json_decode($importData, true);

                    // check if we have existing forms based on code
                    $ids = $this->getFormIdsByCode($dataArray);
                    if (!empty($ids) && (bool) $formData['overwrite_existing']) {
                        $result = $this->updateExistingForm($dataArray, $ids);
                        $resultId = $result->getId();
                        // unset these fields because we already handle them in the updateExistingForm method
                        foreach ($unsetAfter as $unset) {
                            if (in_array($unset, $dataArray)) {
                                unset($dataArray[$unset]);
                            }
                        };
                        // set all other data
                        $result->setData($dataArray);
                        // reset the ID after we used setData() otherwise it'll be NULL
                        $result->setId($resultId);
                    } else {
                        $result = $model->import($importData);
                    }
                    if ($result->getId()) {
                        $result->setIsActive($formData['is_active']);
                        $result->save();
                        $this->log->logInfo(__('Form "%1" successfully imported or updated.', $result->getName()));
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
     * @param $data array
     * @param $ids array
     * @return Form
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function updateExistingForm($data, $ids)
    {
        $formFactory = $this->formFactory->create();

        foreach ($ids as $id) {
            // TODO load is deprecated, but not sure on how to replace this yet.
            $form = $formFactory->load($id);
            // transitional matrix for logic rules
            $logicMatrix = [];
            if ($form->getId()) {
                // import or update fields
                if (!empty($data['fields'])) {
                    foreach ($data['fields'] as $fieldData) {
                        $this->importFields($fieldData, $form->getId(), $logicMatrix);
                    }
                }
                // import or update fieldsets
                if (!empty($data['fieldsets'])) {
                    foreach ($data['fieldsets'] as $fieldsetData) {
                        $this->importFieldset($fieldsetData, $form->getId(), $logicMatrix);
                        if (!empty($fieldsetData['fields'])) {
                            foreach ($fieldsetData['fields'] as $fieldData) {
                                $this->importFields($fieldData, $form->getId(), $logicMatrix);
                            }
                        }
                    }
                }
                // import logic
                if (!empty($data['logic'])) {
                    foreach ($data['logic'] as $logicData) {
                        $this->importLogic($logicData, $logicMatrix);
                    }
                }
                // import store data
                if (!empty($data['store_data'])) {
                    foreach ($data['store_data'] as $storeCode => $storeData) {
                        $this->importStoreData($storeData, $storeCode);
                    }
                }
            }
        }
        return $formFactory;
    }

    /**
     * @param $data
     * @return array
     */
    public function getFormIdsByCode($data)
    {
        $ids = [];
        if (isset($data['code'])) {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('code', $data['code']);
            foreach ($collection->getData() as $form) {
                array_push($ids, $form['id']);
            }
        }
        return $ids;
    }

    protected function importFields($fieldData, $webformId, &$logicMatrix)
    {
        /** @var Field $fieldModel */
        $fieldModel = $this->_fieldFactory->create();
        $field = $fieldModel->load($fieldData['tmp_id']);
        if ($field->getId()) {
            $field->setData($fieldData);
            $field->setData('webform_id', $webformId);
            $field->setId($fieldData['tmp_id']);
            $field->save();
            $logicMatrix['field_' . $fieldData['tmp_id']] = $field->getId();
        } else {
            $fieldModel->setData($fieldData);
            $fieldModel->setData('webform_id', $webformId);
            $fieldModel->save();
            $logicMatrix['field_' . $fieldData['tmp_id']] = $fieldModel->getId();
        }
        // import store data
        if (!empty($fieldData['store_data'])) {
            foreach ($fieldData['store_data'] as $storeCode => $storeData) {
                $storeExists = $this->_webformsHelper->checkStoreCode($storeCode);
                if ($storeExists) {
                    $storeId = $this->_storeManager->getStore($storeCode)->getId();
                    if ($storeId) {
                        $fieldModel->saveStoreData($storeId, $storeData);
                    }
                }
            }
        }
    }

    protected function importFieldset($fieldsetData, $webformId, &$logicMatrix)
    {
        /** @var Fieldset $fieldsetModel */
        $fieldsetModel = $this->_fieldsetFactory->create();
        $fieldset = $fieldsetModel->load($fieldsetData['tmp_id']);
        if ($fieldset->getId()) {
            $fieldset->setData($fieldsetData);
            $fieldset->setData('webform_id', $webformId);
            $fieldset->setId($fieldsetData['tmp_id']);
            $fieldset->save();
            $logicMatrix['fieldset_' . $fieldsetData['tmp_id']] = $fieldset->getId();
        } else {
            $fieldsetModel->setData($fieldsetData);
            $fieldsetModel->setData('webform_id', $webformId);
            $fieldsetModel->save();
            $logicMatrix['fieldset_' . $fieldsetData['tmp_id']] = $fieldsetModel->getId();
        }
        // import store data
        if (!empty($fieldsetData['store_data'])) {
            foreach ($fieldsetData['store_data'] as $storeCode => $storeData) {
                $storeExists = $this->_webformsHelper->checkStoreCode($storeCode);
                if ($storeExists) {
                    $storeId = $this->_storeManager->getStore($storeCode)->getId();
                    if ($storeId) {
                        $fieldsetModel->saveStoreData($storeId, $storeData);
                    }
                }
            }
        }
    }

    protected function importLogic($logicData, &$logicMatrix)
    {
        // import logic rules
        // TODO check if a logicModel already exists by loading based on field id?
        /** @var Logic $logicModel */
        $logicModel = $this->_logicFactory->create()->setData($logicData);
        $logicModel->setData('field_id', $logicMatrix['field_' . $logicData['field_id']]);
        $target = [];
        foreach ($logicData['target'] as $targetData) {
            $prefix = 'field_';
            if (strstr($targetData, 'fieldset_')) {
                $prefix = 'fieldset_';
            }
            if (!empty($logicMatrix[$targetData])) {
                $target[] = $prefix . $logicMatrix[$targetData];
            }
            if ($targetData == 'submit') {
                $target[] = 'submit';
            }
        }

        $logicModel->setData('target', $target);
        $logicModel->save();

        // import store data
        if (!empty($logicData['store_data'])) {
            foreach ($logicData['store_data'] as $storeCode => $storeData) {
                $storeExists = $this->_webformsHelper->checkStoreCode($storeCode);
                if ($storeExists) {
                    $storeId = $this->_storeManager->getStore($storeCode)->getId();

                    if ($storeId) {
                        $target = [];
                        foreach ($storeData['target'] as $targetData) {
                            $prefix = 'field_';
                            if (strstr($targetData, 'fieldset_')) {
                                $prefix = 'fieldset_';
                            }
                            if (!empty($logicMatrix[$targetData])) {
                                $target[] = $prefix . $logicMatrix[$targetData];
                            }
                        }
                        $storeData['target'] = $target;
                        $logicModel->saveStoreData($storeId, $storeData);
                    }
                }
            }
        }
    }

    protected function importStoreData($storeData, $storeCode)
    {
        $this->formFactory->create();
        $storeExists = $this->_webformsHelper->checkStoreCode($storeCode);
        if ($storeExists) {
            $storeId = $this->_storeManager->getStore($storeCode)->getId();
            if ($storeId) {
                $this->formFactory->saveStoreData($storeId, $storeData);
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
