<?php

namespace SolspaceMigration\Library\Mappings;

use craft\base\Field;
use craft\elements\Tag;
use craft\fields\Assets;
use craft\fields\Checkboxes;
use craft\fields\Dropdown;
use craft\fields\MultiSelect;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\RadioButtons;
use craft\fields\RichText;
use craft\fields\Tags;
use craft\helpers\Db;
use craft\models\EntryDraft;
use craft\models\EntryType;
use craft\models\TagGroup;
use craft\records\Element;
use craft\records\Entry;
use function Craft\returnIfSet;
use craft\services\Elements;
use craft\services\Entries;
use craft\services\Fields;
use craft\services\Sections;
use gathercontent\gathercontent\Gathercontent;
use gathercontent\gathercontent\models\MappingModel;
use gathercontent\gathercontent\records\MappingRecord;
use gathercontent\gathercontent\records\RelationTableRecord;
use gathercontent\gathercontent\services\GatherContent_AssetService;
use gathercontent\gathercontent\services\LoggerService;
use SolspaceMigration\Library\Exceptions\GatherContentException;
use SolspaceMigration\Library\Repositories\RelationshipRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use yii\base\Model;

class CraftEntityMapping
{
    /** @var string */
    private $modelClassName;

    /** @var string */
    private $recordClassName;

    /** @var string */
    private $table;

    /** @var array */
    private $lookupBy;

    /** @var array */
    private $attributes;

    /** @var string */
    private $callback;

    /** @var string */
    private $service;

    /** @var string */
    private $afterInsertCallback;

    /** @var string */
    private $afterSaveCallback;

    /** @var string */
    private $beforeSaveCallback;

    /** @var $errorsMessage  */
    public $errorsMessage = null;

    /** @var LoggerService $loggerService */
    public $loggerService;

    public function __construct()
    {
        $this->loggerService = Gathercontent::$plugin->logger;
    }

    /**
     * @param string $sfObjectName
     * @param array  $config
     *
     * @return CraftEntityMapping
     * @throws GatherContentException
     */
    public static function create($sfObjectName, array $config)
    {
        $service    = isset($config["service"]) ? $config["service"] : null;
        $callback    = isset($config["callback"]) ? $config["callback"] : null;
        $model    = isset($config["model"]) ? $config["model"] : null;
        $record    = isset($config["record"]) ? $config["record"] : null;
        $table    = isset($config["table"]) ? $config["table"] : null;
        $lookupBy    = isset($config["lookup_by"]) ? $config["lookup_by"] : null;
        $attributes    = isset($config["attributes"]) ? $config["attributes"] : null;
        $events    = isset($config["events"]) ? $config["events"] : null;

        $errors = [];
        if (!$callback) {
            if (!$service) {
                $errors[] = "Service not defined for Craft Entity mapping";
            }

            if (!preg_match("/^[a-zA-Z0-9_]+::[a-zA-Z0-9_]+$/", $service)) {
                $errors[] = "Service must conform to 'serviceName::methodName' syntax";
            }

            if (!$model) {
                $errors[] = "Model class name not defined for Craft Entity mapping";
            }

            if (!$record) {
                $errors[] = "Record class name not defined for Craft Entity mapping";
            }
        } else {
            if (!preg_match("/^[a-zA-Z0-9_]+::[a-zA-Z0-9_]+$/", $callback)) {
                $errors[] = "Callback must conform to 'serviceName::methodName' syntax";
            }
        }

        if (!$service && !$callback) {
            $errors[] = "Either service or callback must be defined for Craft Entity mapping";
        }

        if (!$table) {
            $errors[] = "Table not defined";
        }

        if ($errors) {
            throw new \Exception(
                sprintf("%s - %s", implode(". ", $errors), $sfObjectName)
            );
        }

        $entity                      = new CraftEntityMapping();
        $entity->modelClassName      = $model;
        $entity->recordClassName     = $record;
        $entity->table               = $table;
        $entity->lookupBy            = $lookupBy;
        $entity->callback            = $callback;
        $entity->service             = $service;
        $entity->attributes          = $attributes;
        $entity->afterInsertCallback = Gathercontent::returnIfSet($events["after_insert"]);
        $entity->afterInsertCallback = isset($events["after_insert"]) ? $events["after_insert"] : null;
        $entity->afterSaveCallback   = isset($events["after_save"]) ? $events["after_save"] : null;
        $entity->beforeSaveCallback  = isset($events["before_save"]) ? $events["before_save"] : null;

        return $entity;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param Model               $model
     * @param SalesforceObjectMapping $objectMapping
     * @param \SObject                $SObject
     */
    public function setModelAttributes(\craft\elements\Entry $model, SalesforceObjectMapping $objectMapping, $SObject)
    {
        if ($this->attributes) {
            $model->typeId = $this->attributes['typeId'];
            $model->sectionId = $this->attributes['sectionId'];
            $model->setAttributes($this->attributes);
        }

        $model->title = $SObject->title;

        $this->setStatus($model);

        /** @var Sections $sectionsService */
        $sectionsService = \Craft::$app->get("sections");

        /** @var EntryType $entryType */
        $entryType = $sectionsService->getEntryTypeById($model->getType()->id);

        $model->fieldLayoutId = $entryType->fieldLayoutId;

        $accessor = PropertyAccess::createPropertyAccessor();

        // Set the SalesforceEntityId for all objects which have content
        try {
            $accessor->setValue($model, "content.salesforceEntityId", $SObject->id);
        } catch (\Exception $e) {
        }

        foreach ($objectMapping->getFieldMappings() as $sfField => $craftField) {
            if (!$craftField) {
                continue;
            }

            $field = CraftFieldMapping::create($sfField, $craftField);

            if (is_null($field->getCraftFieldName()) || $field->getType() === CraftFieldMapping::TYPE_SETTING) {
                continue;
            }

            /** @var Fields $fieldService */
            $fieldService = \Craft::$app->get('fields');

            /** @var Field $fieldModel */
            $fieldModel = $fieldService->getFieldByHandle($field->getCraftFieldName());
            $fieldType = $this->getFieldType($fieldModel);

            $options = [];

            if (in_array($fieldType, [
                    CraftFieldMapping::TYPE_RADIO_BUTTONS,
                    CraftFieldMapping::TYPE_CHECKBOXES,
                    CraftFieldMapping::TYPE_MULTI_SELECT,
                    CraftFieldMapping::TYPE_DROPDOWN,
                ])) {
                /** @var Checkboxes  $fieldModel*/
                $options = $fieldModel->options;
            }

            $value = $field->transformToCraftValue($SObject, $fieldType, $options);

            if ($fieldType == CraftFieldMapping::TYPE_TAGS) {
                $test = 'test';
                /** @var Tags $fieldModel */
                $value = $this->saveTags($fieldModel, $value);
            }

            if ($value == []) {
                $model->setFieldValue($field->getCraftFieldName(), $value);
                continue;
            }

            if (!$value) {
                continue;
            }

            try {
                $model->setFieldValue($field->getCraftFieldName(), $value);
            } catch (\Exception $e) {
                $this->loggerService->warning($e->getMessage());
            }
        }
    }

    private function saveTags(Tags $fieldModel, $tags)
    {
        if (!$tags) {
            return [];
        }

        if (!is_array($tags)) {
            $tags = strip_tags($tags);
            $tags = trim(preg_replace('/\s+/', ' ', $tags));

            if ($tags == '​') {
                return [];
            }

            $tags = explode(', ', $tags);

            if (!$tags) {
                return [];
            }
        }

        $source = $fieldModel->getSourceOptions();
        $source = array_pop($source);

        if (!$source || !array_key_exists('value', $source)) {
            return [];
        }

        $tagGroupId = (int)str_replace('taggroup:', '', $source['value']);

        if (!is_numeric($tagGroupId)) {
            return [];
        }

        /** @var \craft\services\Tags $tagsService*/
        $tagsService = \Craft::$app->get('tags');

        /** @var TagGroup $tagGroup */
        $tagGroup = $tagsService->getTagGroupById($tagGroupId);

        if (!$tagGroup) {
            return [];
        }

        $savedTags = [];

        foreach ($tags as $value) {

            $tag = Tag::find()
                ->groupId($tagGroup->id)
                ->title(Db::escapeParam($value).'*')
                ->one();

            if (!$tag) {
                $tag = new Tag();
                $tag->groupId = $tagGroup->id;
                $tag->fieldLayoutId = $tagGroup->fieldLayoutId;
                $tag->title = $value;
                $tag->validateCustomFields = false;

                $success = \Craft::$app->getElements()->saveElement($tag);

                if (!$success) {
                    continue;
                }
            }

            $savedTags[] = $tag->id;
        }

        return $savedTags;
    }

    private function getFieldType($fieldModel)
    {
        $fieldType = CraftFieldMapping::TYPE_TEXT;

        if ($fieldModel instanceof Assets) {
            return CraftFieldMapping::TYPE_IMAGE;
        }

        if ($fieldModel instanceof RichText) {
            return CraftFieldMapping::TYPE_RICH_TEXT;
        }

        if ($fieldModel instanceof Checkboxes) {
            return CraftFieldMapping::TYPE_CHECKBOXES;
        }

        if ($fieldModel instanceof RadioButtons) {
            return CraftFieldMapping::TYPE_RADIO_BUTTONS;
        }

        if ($fieldModel instanceof Dropdown) {
            return CraftFieldMapping::TYPE_DROPDOWN;
        }

        if ($fieldModel instanceof MultiSelect) {
            return CraftFieldMapping::TYPE_MULTI_SELECT;
        }

        if ($fieldModel instanceof Tags) {
            return CraftFieldMapping::TYPE_TAGS;
        }

        if ($fieldModel instanceof Number) {
            return CraftFieldMapping::TYPE_NUMBER;
        }

        return $fieldType;
    }

    public function setStatus(\craft\elements\Entry $model)
    {
        switch ($this->attributes['status']) {
            case MappingModel::STATUS__ENABLED:
                $model->enabled = true;
                break;
            case MappingModel::STATUS__DISABLED:
                $model->enabled = false;
                break;
            case MappingModel::STATUS__DRAFT:
                $model->enabled = false;
                break;
        }
    }

    /**
     * @param SalesforceObjectMapping $objectMapping
     * @param \SObject                $SObject
     *
     * @return int|null
     */
    public function save(SalesforceObjectMapping $objectMapping, $SObject)
    {
        $relationshipId = null;
        $elementId = $this->getCraftElementId($SObject->id, $objectMapping->getObjectType());
        // If there is no relationship between Craft and SF and "updateOnly" is set to true
        // We skip the storing
        if (!$elementId && $objectMapping->isUpdateOnly()) {
            return null;
        }

        /** @var Model $model */
        $model = null;
        if ($this->service) {
            $model = $this->saveAsService($objectMapping, $SObject);
        } else if ($this->callback) {
            $model = $this->saveAsCallback($objectMapping, $SObject);
        }


        if ($model && $model->getErrors()) {
            $this->logErrors($model);
        }

        if ($model && $model->id) {
            $relationshipId = RelationshipRepository::insertOrUpdateRelationship(
                $model->id,
                $this->getTable(),
                $SObject->id,
                $objectMapping->getObjectType(),
                time()
            );

//            RelationshipRepository::updateSalesforceEntryId($model, $SObject->id);

            // Initiate afterInsert callback
            if (!$elementId && $this->afterInsertCallback) {
                list($service, $method) = explode("::", $this->afterInsertCallback);
                \Craft\craft()->{$service}->{$method}($model, $SObject, $objectMapping);
            }

            // Initiate afterSave callback
            if ($this->afterSaveCallback) {
                list($service, $method) = explode("::", $this->afterSaveCallback);
                \Craft\craft()->{$service}->{$method}($model, $SObject, $objectMapping);
            }
        }

        return $relationshipId;
    }

    /**
     * @param SalesforceObjectMapping $objectMapping
     * @param \SObject                $SObject
     *
     * @return int|null
     */
    private function saveAsCallback(SalesforceObjectMapping $objectMapping, $SObject)
    {
        $elementId = $this->getCraftElementId($SObject->id, $objectMapping->getObjectType());
        list($service, $method) = explode("::", $this->callback);

        /** @var BaseModel $entity */
        return \Craft\craft()->{$service}->{$method}($SObject, $objectMapping, $elementId);
    }

    /**
     * @param SalesforceObjectMapping $objectMapping
     * @param \SObject                $SObject
     *
     * @return int|null
     */
    private function saveAsService(SalesforceObjectMapping $objectMapping, $SObject)
    {
        /** @var \craft\elements\Entry $entity */
        $entity = $this->getOrCreate($SObject->id, $objectMapping->getObjectType(), $SObject);
        $this->setModelAttributes($entity, $objectMapping, $SObject);

        list($service, $method) = explode("::", $this->service);

         $success = \Craft::$app->get($service)->{$method}($entity);

        return $entity;
    }

    /**
     * @param string $sfId
     * @param string $sfObjectType
     *
     * @return int|null
     */
    private function getCraftElementId($sfId, $sfObjectType)
    {
        return RelationshipRepository::getElementId($sfId, $sfObjectType, $this->table);
    }

    /**
     * @param string $sfId
     * @param string $sfObjectType
     * @param \SObject $SObject
     * @return BaseModel|mixed
     */
    private function getOrCreate($sfId, $sfObjectType, $SObject)
    {
        /** @var \craft\elements\Entry $model */
        $model = new \craft\elements\Entry();

        if ($this->attributes['status'] == MappingModel::STATUS__DRAFT) {
            $model = new EntryDraft();
        }

        $elementId = $this->getCraftElementId($sfId, $sfObjectType);

        if ($elementId) {
            /** @var Elements $elementsService */
            $elementsService = \Craft::$app->get('elements');

            /** @var Element $record */
            $record = $elementsService->getElementById($elementId);

            // Delete Relationship Record if we cannot find corresponding Craft's entry. Probably because it has deleted
//            if (!$record) {
//                $relationRecord = RelationTableRecord::find()
//                    ->where([
//                        'craftElementId' => $elementId,
//                    ])
//                    ->one();
//
//                if ($relationRecord) {
//                    $relationRecord->delete();
//                }
//            }

            if ($record) {
                $model->setAttributes($record->getAttributes(), false);
            }

//            $record = call_user_func($this->recordClassName . "::model")->findById($elementId);
//            $model  = call_user_func($this->modelClassName . "::populateModel", $record);
        } else {
            if ($this->lookupBy) {
                $lookupAttributes = [];
                foreach ($this->lookupBy as $sfField => $craftField) {
                    $field                         = CraftFieldMapping::create($sfField, $craftField);
                    $value                         = $field->transformToCraftValue($SObject);
                    $lookupAttributes[$craftField] = $value;
                }

                $record = call_user_func($this->recordClassName . "::model")->findByAttributes($lookupAttributes);
                if ($record) {
                    /** @var Model $model */
                    $model = call_user_func($this->modelClassName . "::populateModel", $record);
                } else {
                    /** @var Model $model */
                    $model = new $this->modelClassName();
                }
            }
        }

        return $model;
    }

    /**
     * Sets errors message
     *
     * @param $message
     */
    public function setErrorsMessage($message)
    {
        $this->errorsMessage = $message;
    }

    /**
     * Logs any errors a Model has
     * if any
     *
     * @param Model $model
     */
    private function logErrors(Model $model)
    {
        $errors = [];
        foreach ($model->getErrors() as $field => $error) {
            $errors[] = "$field: " . implode(", ", $error);
        }

        if (empty($errors)) {
            return;
        }

        $message = sprintf(
            "Errors while saving %s: %s",
            get_class($model),
            implode(", ", $errors)
        );

        $this->setErrorsMessage($message);
        $this->loggerService->log($message, 1, 'CraftEntityMapping');
    }
}
