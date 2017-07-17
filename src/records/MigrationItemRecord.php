<?php

namespace gathercontent\gathercontent\records;

use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use gathercontent\gathercontent\services\GatherContent_GatherContentService;
use yii\db\ActiveQuery;

class MigrationItemRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_migration_items}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MigrationRecord
     */
    public function getMigration(): ActiveQuery
    {
        return $this->hasOne(MigrationRecord::className(), ['id' => 'migrationId']);
    }

    /**
     * @return ActiveQuery|MappingRecord
     */
    public function getMapping(): ActiveQuery
    {
        return $this->hasOne(MappingRecord::className(), ['id' => 'mappingId']);
    }

    /**
     * @return ActiveQuery|MappingRecord
     */
    public function getRelationship(): ActiveQuery
    {
        return $this->hasOne(RelationTableRecord::className(), ['id' => 'relationshipId']);
    }

    public function itemUrl()
    {
        /** @var GatherContent_GatherContentService $gcService */
        $gcService = Gathercontent::$plugin->gatherContent_gatherContent;
        return $gcService->getItemUrl($this->itemId, $this->mapping->gatherContentAccountId);
    }
}
