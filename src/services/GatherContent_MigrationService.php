<?php

namespace gathercontent\gathercontent\services;

use craft\base\Volume;
use craft\db\Query;
use craft\services\Volumes;
use gathercontent\gathercontent\Gathercontent;
use Craft;
use craft\base\Component;
use gathercontent\gathercontent\models\MigrationItemModel;
use gathercontent\gathercontent\models\MigrationModel;
use gathercontent\gathercontent\records\MappingRecord;
use gathercontent\gathercontent\records\MigrationItemRecord;
use gathercontent\gathercontent\records\MigrationRecord;
use SolspaceMigration\Library\Mappings\SalesforceObjectMapping;

class GatherContent_MigrationService extends Component
{
    /**
     * @return MigrationRecord
     */
    public function newMigrationRecord()
    {
        $migrationRecord = new MigrationRecord();
        $migrationRecord->save();

        return $migrationRecord;
    }

    /**
     * @return MigrationRecord
     */
    public function getMigrationById($migrationId)
    {
        return MigrationRecord::find()->andWhere(['id' => $migrationId])->one();
    }

    public function saveItemToMigration(MigrationRecord $migrationRecord, $mappingId, $itemId, $relationshipId, $successful = true)
    {
        $migrationItemRecord = new MigrationItemRecord();
        $migrationItemRecord->migrationId = $migrationRecord->id;
        $migrationItemRecord->mappingId = $mappingId;
        $migrationItemRecord->itemId = $itemId;
        $migrationItemRecord->relationshipId = $relationshipId;

        if ($successful) {
            $status = MigrationItemModel::STATUSS__SUCCESS;
        } else {
            $status = MigrationItemModel::STATUSS__FAIL;
        }

        $migrationItemRecord->status = $status;

        $success = $migrationItemRecord->save();

        if (!$success) {
            return false;
        }
        return true;
    }
}
