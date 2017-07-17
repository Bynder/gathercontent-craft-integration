<?php

namespace gathercontent\gathercontent\records;

use craft\elements\Entry;
use craft\services\Entries;
use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * Class ChiSfSync_RelationTableRecord
 *
 * @property int      $craftElementId
 * @property string   $craftElementTable
 * @property string   $sfEntityId
 * @property string   $sfObject
 * @property DateTime $sfLastModifiedDate
 */
class RelationTableRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_relationships}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MigrationItemRecord
     */
    public function getItems(): ActiveQuery
    {
        return $this->hasMany(MigrationItemRecord::className(), ['relationshipId' => 'id']);
    }

    public function entry()
    {
        /** @var Entries $entriesService */
        $entriesService = Craft::$app->get('entries');

        /** @var Entry $entry */
        $entry = $entriesService->getEntryById($this->craftElementId);
        return $entry;
    }

    public function entryUrl()
    {
        /** @var Entries $entriesService */
        $entriesService = Craft::$app->get('entries');

        /** @var Entry $entry */
        $entry = $entriesService->getEntryById($this->craftElementId);
        return $entry->getCpEditUrl();
    }
}
