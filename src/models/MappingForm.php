<?php

namespace gathercontent\gathercontent\models;

use craft\base\Field;
use craft\fields\Assets;
use craft\services\Fields;
use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Model;
use gathercontent\gathercontent\records\MappingFieldRecord;
use gathercontent\gathercontent\records\MappingRecord;
use gathercontent\gathercontent\records\MappingTabRecord;
use gathercontent\gathercontent\services\GatherContent_GatherContentService;
use gathercontent\gathercontent\services\LoggerService;

class MappingForm extends Model
{
    const LOG_CATEGORY = 'Mapping Form';

    public $craftSectionId;
    public $gatherContentTemplateId;
    public $gatherContentProjectId;
    public $tabsObjects;
    public $tabs;
    public $elements;
    public $craftEntryTypeId;
    public $gatherContentAccountId;
    public $elementErrors;
    public $craftStatus;
    public $globalError;

    public function rules(): array
    {
        return [
            [
                [
                    'craftSectionId',
                    'gatherContentTemplateId',
                    'gatherContentProjectId',
                    'craftEntryTypeId',
                    'gatherContentAccountId',
                    'craftStatus',
                ],

                'required',
            ],
        ];
    }

    public function attributeLabels()
    {
        return [
            'gatherContentTemplateId' => 'GatherContent Template',
            'craftSectionId' => 'Craft Section',
            'gatherContentProjectId' => 'GatherContent Project',
            'craftEntryTypeId' => 'Craft Entry Type',
            'gatherContentAccountId' => 'GatherContent Account',
            'craftStatus' => 'Craft Status',
        ];
    }

    /** @var  GatherContent_GatherContentService $gatherContentService */
    private $gatherContentService;

    /** @var  LoggerService $loggerService */
    private $loggerService;

    public function __construct($attributes = null)
    {
        $this->gatherContentService = Gathercontent::$plugin->gatherContent_gatherContent;
        $this->loggerService = Gathercontent::$plugin->logger;

        parent::__construct($attributes);
    }

    public static function create()
    {
        $model = new static();

        return $model;
    }

    public function populate(MappingRecord $mappingRecord)
    {
        $this->setAttributes($mappingRecord->getAttributes(), false);
        $this->tabsObjects = $mappingRecord->tabs;

        return $this;
    }

    public function populateWithPost($post)
    {
        $nullableAttributes = $this->getNullableAttributes();

        foreach ($nullableAttributes as $attribute) {
            if (!in_array($attribute, $post)) {
                $this->{$attribute} = null;
            }
        }

        $this->setAttributes($post, false);

        return $this;
    }

    public function validateElements()
    {
        $this->validateELementUniquenes();

        if (!empty($this->elementErrors) || !empty($this->globalError)) {
            return false;
        }

        $this->validateElementTypes();

        if (!empty($this->elementErrors) || !empty($this->globalError)) {
            return false;
        }

        return true;
    }

    public function validateELementUniquenes()
    {
        $valid = true;

        if (!$this->tabs) {
            return true;
        }

        $counter = [];

        foreach ($this->tabs as $tabId => $tab) {

            if (!array_key_exists('elements', $tab) && empty($tab['elements'])) {
                continue;
            }

            foreach ($tab['elements'] as $elementName => $fieldHandle) {

                if (!$fieldHandle || $fieldHandle === 'null') {
                    continue;
                }

                if (!array_key_exists($fieldHandle, $counter)) {
                    $counter[$fieldHandle]['count'] = 1;
                    $counter[$fieldHandle]['elements'] = [$elementName];
                } else {
                    $counter[$fieldHandle]['count']++;
                    $counter[$fieldHandle]['elements'][] = $elementName;
                }

            }
        }

        if (!$counter) {
            $this->globalError = 'At least one element has to be mapped';
            return false;
        }

        foreach ($counter as $fieldHandle => $row) {
            if ($row['count'] > 1) {
                foreach ($row['elements'] as $elementName) {
                    /** @var Fields $fieldService */
                    $fieldService = \Craft::$app->get('fields');

                    /** @var Field $fieldModel */
                    $fieldModel = $fieldService->getFieldByHandle($fieldHandle);
                    $this->elementErrors[$elementName] = 'Cannot Map ' . $fieldModel->name . ' multiple times';
                }
                $valid = false;
            }
        }

        return $valid;
    }

    public function validateElementTypes()
    {
        $valid = true;

        if (!$this->tabs) {
            return true;
        }

        foreach ($this->tabs as $tabId => $tab) {

            if (!array_key_exists('elements', $tab) && empty($tab['elements'])) {
                continue;
            }

            /** @var GatherContent_GatherContentService $gatherContentService */
            $gatherContentService = $this->gatherContentService;
            $elements = $gatherContentService->getElementsByTemplateId($this->gatherContentTemplateId, true, true);

            foreach ($tab['elements'] as $elementName => $fieldHandle) {

                if (!$fieldHandle) {
                    continue;
                }

                if (!array_key_exists($elementName, $elements)) {
                    continue;
                }

                $elementInfo = $elements[$elementName];

                $fieldType = $gatherContentService->getFieldType($fieldHandle);

                if (!$fieldType) {
                    continue;
                }

                if (!array_key_exists('type', $elementInfo)) {
                    continue;
                }

                $compatable = $gatherContentService->isElementTypeAndFieldTypeCompatable($elementInfo['type'], $fieldType);

                if (!$compatable) {
                    $fieldModel = new $fieldType();
                    $fieldTypeName = $fieldModel::displayName();
                    $this->elementErrors[$elementName] = 'Cannot be mapped with Field Type ' . $fieldTypeName;
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    public function save()
    {
        $mappingRecord = $this->getMappingRecord();
        $mappingRecordId = $this->saveMappingRecord($mappingRecord);

        if (!$mappingRecordId) {
            return false;
        }

        $this->saveMappingTabs($mappingRecordId);

        return true;
    }

    public function update($mappingId)
    {
        $mappingRecord = $this->getMappingRecord($mappingId);
        $mappingRecordId = $this->saveMappingRecord($mappingRecord);

        if (!$mappingRecordId) {
            return false;
        }

        $this->saveMappingTabs($mappingRecordId, true);

        return true;
    }

    public function getMappingRecord($mappingId = null)
    {
        if ($mappingId === null) {
            $mappingRecord = new MappingRecord();
        } else {
            $mappingRecord = MappingRecord::find()->andWhere(['id' => $mappingId])->one();
        }

        $mappingRecord->craftSectionId = $this->craftSectionId;
        $mappingRecord->gatherContentTemplateId = $this->gatherContentTemplateId;
        $mappingRecord->gatherContentProjectId = $this->gatherContentProjectId;
        $mappingRecord->craftEntryTypeId = $this->craftEntryTypeId;
        $mappingRecord->gatherContentTemplateName = $this->gatherContentService->getTemplatetNameById($this->gatherContentTemplateId);
        $mappingRecord->gatherContentProjectName = $this->gatherContentService->getProjectNameById($this->gatherContentProjectId);
        $mappingRecord->lastImportTimestamp = GatherContent::getCurrentDateTime();
        $mappingRecord->lastOffset = 0;
        $mappingRecord->migrating = false;
        $mappingRecord->gatherContentAccountId = $this->gatherContentAccountId;
        $mappingRecord->craftStatus = $this->craftStatus;

        return $mappingRecord;
    }

    public function saveMappingTabs($mappingId, $update = false)
    {
        $mappingTabRecords = [];

        if (!$this->tabs) {
            return true;
        }

        foreach ($this->tabs as $tabId => $tab) {

            if (!array_key_exists('elements', $tab) && empty($tab['elements'])) {
                continue;
            }

            $mappingTabRecord = null;

            if ($update && $this->tabsObjects) {
                foreach ($this->tabsObjects as $tabObject) {
                    /** @var MappingTabRecord %tabObject */

                    if ($tabObject->gatherContentTabId  == $tabId) {
                        $mappingTabRecord = $tabObject;
                    }
                }
            }

            if ($mappingTabRecord === null) {
                $mappingTabRecord = new MappingTabRecord();
            }

            $mappingTabRecord->gatherContentTabId = $tabId;
            $mappingTabRecord->mappingId = $mappingId;
            $success = $mappingTabRecord->save();

            if (!$success) {
                $this->loggerService->error('Could not successfully save GatherContent_MappingTabRecord', self::LOG_CATEGORY);
                return false;
            }

            foreach ($tab['elements'] as $elementName => $fieldHandle) {

                $mappingRecord = null;

                if ($update && !$mappingTabRecord->isNewRecord) {
                    $fieldRecords = $mappingTabRecord->fields;

                    if ($fieldRecords) {
                        foreach ($fieldRecords as $fieldRecord) {
                            /** @var MappingFieldRecord $fieldRecord */

                            if ($fieldRecord->gatherContentElementName  == $elementName) {
                                $mappingRecord = $fieldRecord;
                            }
                        }
                    }
                }

                if (!$fieldHandle || $fieldHandle === 'null') {

                    if ($mappingRecord && !$mappingRecord->isNewRecord) {
                        $mappingRecord->delete();
                    }

                    continue;
                }

                if ($mappingRecord === null) {
                    $mappingRecord = new MappingFieldRecord();
                }

                $mappingRecord->craftFieldHandle = $fieldHandle;
                $mappingRecord->gatherContentElementName = $elementName;
                $mappingRecord->tabId = $mappingTabRecord->id;
                $success = $mappingRecord->save();

                if (!$success) {
                    $this->loggerService->error('Could not successfully save GatherContent_MappingFieldRecord', self::LOG_CATEGORY);
                }
            }
        }

        return true;
    }

    public function saveMappingRecord(MappingRecord $mappingRecord)
    {
        $success = $mappingRecord->save(false);

        if (!$success) {
            $this->loggerService->error('Could not successfully save GatherContent_MappingRecord', self::LOG_CATEGORY);
            return false;
        }

        return $mappingRecord->id;
    }

    public function getFieldByTabAndElement($tabId, $elementId)
    {
        $result = null;

        $tabs = $this->tabsObjects;

        if ($tabs) {
            foreach ($tabs as $tab){

                /** @var MappingTabRecord $tab */

                if ($tab->gatherContentTabId == $tabId) {

                    $fields = $tab->fields;

                    if ($fields) {
                        foreach ($fields as $field) {

                            /** @var MappingFieldRecord $field */
                            if ($field->gatherContentElementName == $elementId) {
                                $result = $field;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    public function getNullableAttributes()
    {
        return [
              'craftSectionId',
              'gatherContentTemplateId',
              'gatherContentProjectId',
              'craftEntryTypeId',
              'gatherContentAccountId',
        ];
    }
}