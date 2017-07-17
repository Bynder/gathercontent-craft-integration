<?php

namespace SolspaceMigration\Library\Mappings;

use function Craft\returnIfSet;
use gathercontent\gathercontent\Gathercontent;
use SolspaceMigration\Library\Exceptions\GatherContentException;
use SolspaceMigration\Library\Repositories\RelationshipRepository;
use Craft\BaseModel;
use Craft\ChiSfConnectorService;
use Craft\Craft;
use Craft\LogLevel;

class SalesforceObjectMapping
{
    const INTEGRATION_SYNC   = "sync";
    const INTEGRATION_IMPORT = "import";
    const INTEGRATION_EXPORT = "export";

    /** @var array */
    private static $allowedIntegrationTypes = [
        self::INTEGRATION_SYNC,
        self::INTEGRATION_IMPORT,
        self::INTEGRATION_EXPORT,
    ];

    /** @var string */
    public $integrationType;

    /** @var string */
    public $mappingName;

    /** @var string */
    public $objectType;

    /** @var array */
    public $fields;

    /** @var array */
    public $fieldMappings;

    /** @var CraftEntityMapping */
    public $entityMapping;

    /** @var array */
    public $conditions;

    /** @var array */
    public $uploadAttributes;

    /** @var bool */
    public $updateOnly;

    /** @var bool */
    public $insertCraftId;

    /** @var null */
    public $errorsMessage = null;

    /**
     * Factory method for creating SalesforceObjectMapping objects
     *
     * @param string $objectType
     * @param array  $config
     *
     * @return SalesforceObjectMapping
     * @throws ChiSfSyncException
     */
    public static function create($objectType, $config)
    {
        if (!isset($config["fields"])) {
            $config["fields"] = [];
        }

        if (!isset($config["conditions"])) {
            $config["conditions"] = [];
        }

        if (!isset($config["craft_entity"])) {
            throw new \Exception(sprintf("Craft Entity data not defined for %s SF Object", $objectType));
        }

        $integrationType = isset($config["integration"]) ? $config["integration"] : self::INTEGRATION_SYNC;

        if (!in_array($integrationType, self::$allowedIntegrationTypes)) {
            throw new \Exception(
                sprintf(
                    "Integration type '%s' not allowed. Allowed types are: %s",
                    $integrationType,
                    implode(", ", self::$allowedIntegrationTypes)
                )
            );
        }

        $mapping = new SalesforceObjectMapping();

        if (isset($config["object"])) {
            $mapping->objectType  = $config["object"];
            $mapping->mappingName = $objectType;
        } else {
            $mapping->objectType  = $objectType;
            $mapping->mappingName = $objectType;
        }

        $mapping->integrationType  = $integrationType;
        $mapping->fieldMappings    = $config["fields"];
        $mapping->fields           = array_keys($config["fields"]);
        $mapping->fields[]         = "LastModifiedDate";
        $mapping->entityMapping    = CraftEntityMapping::create($objectType, $config["craft_entity"]);
        $mapping->conditions       = $config["conditions"];
        $mapping->updateOnly       = isset($config["update_only"]) ? (bool)$config["update_only"] : false;
        $mapping->uploadAttributes = isset($config["upload_attributes"]) ? (bool)$config["upload_attributes"] : false;
        $mapping->insertCraftId    = isset($config["insert_craft_id"]) ? (bool)$config["insert_craft_id"] : false;

        return $mapping;
    }

    /**
     * Private SalesforceObjectMapping constructor.
     * - Prevents creation without config
     */
    public function __construct()
    {
    }

    /**
     * @return bool
     */
    public function isImportOnly()
    {
        return $this->integrationType === self::INTEGRATION_IMPORT;
    }

    /**
     * @return bool
     */
    public function isExportOnly()
    {
        return $this->integrationType === self::INTEGRATION_EXPORT;
    }

    /**
     * @return string
     */
    public function getObjectType()
    {
        return $this->objectType;
    }

    /**
     * @return array
     */
    public function getFieldMappings()
    {
        return $this->fieldMappings;
    }

    /**
     * @return CraftEntityMapping
     */
    public function getEntityMapping()
    {
        return $this->entityMapping;
    }

    /**
     * @return bool
     */
    public function isUpdateOnly()
    {
        return $this->updateOnly;
    }

    /**
     * Create and return a SOQL SELECT statement
     *
     * @param array $idList
     * @param bool  $forceSyncAll
     *
     * @return null|string
     */
    public function getSOQLSelect(array $idList = null, $forceSyncAll = false)
    {
        $fields = $this->getFieldList();

        $conditions = Gathercontent::returnIfSet($this->conditions, []);

        $timestamp = RelationshipRepository::getLatestTimestamp(
            $this->objectType,
            $this->entityMapping->getTable()
        );

        if (!is_null($idList) && !empty($idList)) {
            $conditions[] = sprintf("Id IN ('%s')", implode("','", $idList));
        }

        if (!$forceSyncAll && empty($idList)) {
            if ($timestamp) {
                $conditions[] = "LastModifiedDate > " . date("Y-m-d\TH:i:s\Z", $timestamp);
            }
        }

        if ($this->updateOnly && !$timestamp) {
            return null;
        }

        $query = "";
        $query .= sprintf("SELECT %s ", implode(", ", $fields));
        $query .= sprintf("FROM %s ", $this->objectType);
        $query .= $conditions ? " WHERE " . implode(" AND ", $conditions) . " " : "";
        $query .= "ORDER BY " . $this->objectType . ".LastModifiedDate ASC ";

        //$query .= "LIMIT 100";

        return $query;
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
     * Update or Insert a Craft Object
     *
     * @param \SObject $SObject
     *
     * @return int|null - Craft element Id
     */
    public function upsertCraftEntity($SObject)
    {
        $entityMapping = $this->getEntityMapping();
        $result = $entityMapping->save($this, $SObject);

        if ($entityMapping->errorsMessage) {
            $this->setErrorsMessage($entityMapping->errorsMessage);
        }

        return $result;
    }

    /**
     * Update or Insert an SF Object
     *
     * @param BaseModel $model
     * @param string    $salesforceId
     * @param array     $additionalValues
     *
     * @return null|string - Salesforce entity Id
     */
    public function upsertSalesforceEntity(
        BaseModel $model,
        string $salesforceId = null,
        array $additionalValues = null
    ) {
        $object = new \SObject();
        $this->setSObjectAttributes($object, $model, $salesforceId, $additionalValues);

        $connection = ChiSfConnectorService::getConnection();

        if ($salesforceId) {
            $result = $connection->update([$object]);
        } else {
            $result = $connection->create([$object]);
        }

        $salesforceId = $this->handleResult($result);

        if (is_null($salesforceId)) {
            return null;
        }

        RelationshipRepository::insertOrUpdateRelationship(
            $model->id,
            $this->getEntityMapping()->getTable(),
            $salesforceId,
            $this->getObjectType(),
            time()
        );

//        RelationshipRepository::updateSalesforceEntryId($model, $salesforceId);

        return $salesforceId;
    }

    /**
     * @param BaseModel $model
     *
     * @return string|null
     */
    public function insertSalesforceEntity(BaseModel $model)
    {
        $object = new \SObject();
        $this->setSObjectAttributes($object, $model);

        $connection = ChiSfConnectorService::getConnection();

        $result       = $connection->create([$object]);
        $salesforceId = $this->handleResult($result);

        if (is_null($salesforceId)) {
            return null;
        }

        RelationshipRepository::insertOrUpdateRelationship(
            $model->id,
            $this->getEntityMapping()->getTable(),
            $salesforceId,
            $this->getObjectType(),
            time()
        );

//        RelationshipRepository::updateSalesforceEntryId($model, $salesforceId);

        return $salesforceId;
    }

    /**
     * @param BaseModel $model
     * @param string    $salesforceId
     *
     * @return bool
     */
    public function linkSfEntityWithCraft(BaseModel $model, string $salesforceId)
    {
        $connection = ChiSfConnectorService::getConnection();

        /** @var \SObject $SObject */
        $SObjectList = $connection->retrieve(
            implode(",", $this->getFieldList()),
            $this->getObjectType(),
            [$salesforceId]
        );
        $SObject     = reset($SObjectList);

        if (!$SObject) {
            return false;
        }

        $this->getEntityMapping()->setModelAttributes($model, $this, $SObject);

        RelationshipRepository::insertOrUpdateRelationship(
            $model->id,
            $this->getEntityMapping()->getTable(),
            $salesforceId,
            $this->getObjectType(),
            time()
        );

//        RelationshipRepository::updateSalesforceEntryId($model, $salesforceId);

        return true;
    }

    /**
     * @return array
     */
    public function getFieldList()
    {
        $fields = $this->fields;
        if (!in_array("Id", $fields)) {
            $fields[] = "Id";
        }

        return $fields;
    }

    /**
     * @param \SObject  $SObject
     * @param BaseModel $model
     * @param string    $salesforceId
     * @param array     $additionalValues
     */
    public function setSObjectAttributes(
        \SObject $SObject,
        BaseModel $model,
        string $salesforceId = null,
        array $additionalValues = null
    ) {
        $SObject->type = $this->getObjectType();

        if ($salesforceId) {
            $SObject->Id = $salesforceId;
        }

        // Set the CraftElementId for all entities
        $objectFields = [];

        if ($this->insertCraftId) {
            $objectFields["CraftElementId__c"] = $model->id;
        }

        $fields = $this->getFieldMappings();
        foreach ($fields as $sfField => $craftField) {
            if (!$craftField || $sfField === "Id") {
                continue;
            }

            $field = CraftFieldMapping::create($sfField, $craftField);
            $value = $field->transformToSalesforceValue($model);

            if (!$field->isReadOnly() && $value) {
                $objectFields[$field->getSalesforceFieldName()] = $value;
            }
        }

        if ($this->uploadAttributes) {
            foreach ($this->uploadAttributes as $key => $value) {
                $objectFields[$key] = $value;
            }
        }

        if (!empty($additionalValues)) {
            foreach ($additionalValues as $key => $value) {
                $objectFields[$key] = $value;
            }
        }

        $SObject->fields = $objectFields;
    }

    /**
     * @param array $results
     *
     * @return string|null
     */
    public function handleResult(array $results)
    {
        // Get the first result, since we'll be receiving only one
        $result = reset($results);
        if (!$result->success) {
            $messages = [];
            foreach ($result->errors as $error) {
                $messages[] = $error->message;
            }

            $message = sprintf(
                "%s: %s",
                $this->getObjectType(),
                implode(", ", $messages)
            );

            Craft::log($message, LogLevel::Error, true, "application", "chiSfSync");

            return null;
        }

        return $result->id;
    }
}
