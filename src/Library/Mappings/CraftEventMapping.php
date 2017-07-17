<?php

namespace SolspaceMigration\Library\Mappings;

use ChiSfSync\Library\Exceptions\ChiSfSyncException;
use ChiSfSync\Library\Repositories\RelationshipRepository;
use Craft\BaseModel;
use Craft\ChiSfSync_SalesforceSyncService;
use Craft\Event;

class CraftEventMapping
{
    /** @var array */
    private $conditions;

    /** @var string */
    private $event;

    /** @var string */
    private $modelParamKey;

    /** @var string */
    private $callback;

    /** @var SalesforceObjectMapping */
    private $mapping;

    /** @var bool */
    private $updateOnly;

    /** @var array */
    private $attributes;

    /**
     * @param string $mappingName
     * @param array  $config
     *
     * @return CraftEventMapping
     * @throws ChiSfSyncException
     */
    public static function create(string $mappingName, array $config): CraftEventMapping
    {
        $conditions    = $config["conditions"] ?? [];
        $event         = $config["event"] ?? null;
        $modelParamKey = $config["model_param_key"] ?? null;
        $callback      = $config["callback"] ?? null;
        $mapping       = $config["mapping"] ?? null;
        $attributes    = $config["attributes"] ?? null;
        $updateOnly    = isset($config["update_only"]) ? (bool)$config["update_only"] : false;

        if (!$event) {
            throw new ChiSfSyncException(sprintf("Event not specified in %s event mapping", $mappingName));
        }

        if (!$mapping) {
            throw new ChiSfSyncException(sprintf("MappingModel not specified in %s event mapping", $mappingName));
        }

        $eventMapping                = new CraftEventMapping();
        $eventMapping->event         = $event;
        $eventMapping->modelParamKey = $modelParamKey;
        $eventMapping->conditions    = $conditions;
        $eventMapping->callback      = $callback;
        $eventMapping->mapping       = \Craft\chisfsync()->getSalesforceObjectMapping($mapping);
        $eventMapping->updateOnly    = $updateOnly;
        $eventMapping->attributes    = $attributes;

        return $eventMapping;
    }

    /**
     * Private CraftEventMapping constructor.
     */
    private function __construct()
    {
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * @param Event $event
     */
    public function triggerSyncIfEventValid(Event $event)
    {
        // Do not sync items to Salesforce while Salesforce->Craft sync is active
        if (\Craft\chisfsync()->salesforceSync->isSalesforceToCraftSyncInitiated()) {
            return;
        }

        /** @var BaseModel $model */
        $model = $event->params[$this->modelParamKey];

        // If conditions aren't met - do not trigger the sync
        // A simple check for now, until more complex things are needed
        if (!empty($this->conditions)) {
            foreach ($this->conditions as $key => $value) {
                if (is_array($value)) {
                    if (!in_array($model->getAttribute($key), $value)) {
                        return;
                    }
                } else if ($model->getAttribute($key) != $value) {
                    return;
                }
            }
        }

        $salesforceId = null;
        if ($model->id) {
            $salesforceId = RelationshipRepository::getSalesforceEntityId(
                $this->mapping->getObjectType(),
                $model->id,
                $this->mapping->getEntityMapping()->getTable()
            );
        }

        // Do not trigger the sync if this is an "updateOnly" mapping
        // And no SF Entity ID is mapped to this element
        if ($this->updateOnly && !$salesforceId) {
            return;
        }

        if ($this->callback) {
            list($service, $method) = explode("::", $this->callback);
            \Craft\craft()->{$service}->{$method}($event, $this->mapping, $salesforceId);

            return;
        }

        // Just initiate the upsert if no callback specified
        $this->mapping->upsertSalesforceEntity($model, $salesforceId);
    }
}
