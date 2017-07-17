<?php

namespace gathercontent\gathercontent\services;

use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use gathercontent\gathercontent\records\MappingRecord;
use SolspaceMigration\Library\Mappings\SalesforceObjectMapping;

GatherContent::loadDependencies();

class GatherContent_DatabaseMigrationService extends Component
{
    /**
     * This flag will make sure Craft->Salesforce sync doesn't happen while
     * Salesforce->Craft sync is happening
     *
     * @var bool
     */
    private $salesforceToCraftSyncInitiated = false;

    /** @var bool */
    protected $allowAnonymous = true;
    
    /** @var GatherContent_MainService $gatherContentService */
    private $gatherContentService;

    /** @var ToolsService $toolsService */
    private $toolsService;
    
    public function __construct(array $config = [])
    {
        $this->gatherContentService = Gathercontent::$plugin->gatherContent_main;
        $this->toolsService = Gathercontent::$plugin->tools;

        parent::__construct($config);
    }

    /**
     * Initializes all object migration
     *
     * @param array $objectTypesToSync - a list of mapping names to sync
     * @param array $specificIdList - a list of specific SF ID's to sync
     * @param bool $forceSync - if TRUE: will force sync all items, even old ones
     */
    public function initMigration(array $objectTypesToSync = null, $migrationId = null, array $specificIdList = null, $forceSync = false)
    {
        $result = [
            'success' => false,
            'finished' => false,
            'error' => [],
        ];

        $this->salesforceToCraftSyncInitiated = true;

        if (!empty($objectTypesToSync) && count($objectTypesToSync) === 1) {
            $config = $this->gatherContentService->getSfToCraftConfig(array_pop($objectTypesToSync));
        }else {
            $config = $this->gatherContentService->getSfToCraftConfig();
        }

        foreach ($config as $objectType => $data) {
            $nextPageNumber = null;

            if (!empty($objectTypesToSync)) {
                if (!in_array($objectType, $objectTypesToSync)) {
                    continue;
                }
            }

            $mapping = SalesforceObjectMapping::create($objectType, $data);

            // Pass if the mapping is set to export from craft only
            if ($mapping->isExportOnly()) {
                continue;
            }

            if ($this->toolsService->isConsole()) {
                echo "Migrating '$objectType' ";
            }

            /** @var MappingRecord $mappingRecord */
            $mappingRecord = MappingRecord::find()
                ->andWhere(['gatherContentTemplateId' => $mapping->mappingName])
                ->andWhere(['deactive' => false])
                ->one();

            if (!$mappingRecord) {
                continue;
            }

            /** @var GatherContent_MigrationService $migrationService */
            $migrationService = Gathercontent::$plugin->gatherContent_migration;

            $gatherContentService = $this->gatherContentService;
            $data = $gatherContentService->getData($mapping, $specificIdList, $forceSync, $mappingRecord->lastOffset);

            if ($migrationId === null) {
                $migrationRecord = $migrationService->newMigrationRecord();
            } else {
                $migrationRecord = $migrationService->getMigrationById($migrationId);

                if (!$migrationRecord) {
                    $migrationRecord = $migrationService->newMigrationRecord();
                }
            }

            $count = 0;
            foreach ($data as $object) {
                $relationshipId = $mapping->upsertCraftEntity($object);

                $success = false;

                if ($relationshipId !== null) {
                    $success = true;
                }

                $migrationService->saveItemToMigration(
                    $migrationRecord,
                    $mappingRecord->id,
                    $object->id,
                    $relationshipId,
                    $success
                );

                $count++;

                if ($this->toolsService->isConsole()) {
                    echo ".";
                }
            }

            if ($this->toolsService->isConsole()) {
                echo " complete ($count items)\r\n";
            }

            if ($mapping->errorsMessage) {
                $result['success'] = false;
                $result['error']['message'] = $mapping->errorsMessage;
            } else {
                /** @var GatherContent_MappingService $mappingService */
                $mappingService = Gathercontent::$plugin->gatherContent_mapping;
                $mappingService->updateJustImported($mappingRecord, $gatherContentService->getNewOffset(), $gatherContentService->getFinishedMigration());
                $result['finished'] = $gatherContentService->getFinishedMigration();
                $result['migrationId'] = $migrationRecord->id;
                $result['success'] = true;
            }

            return $result;
        }
    }

    /**
     * @return bool
     */
    public function isSalesforceToCraftSyncInitiated()
    {
        return $this->salesforceToCraftSyncInitiated;
    }
}
