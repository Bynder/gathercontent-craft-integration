<?php

namespace gathercontent\gathercontent\records;

use craft\records\Entry;
use craft\records\EntryType;
use craft\services\Sections;
use gathercontent\gathercontent\Gathercontent;

use Craft;
use craft\db\ActiveRecord;
use yii\db\ActiveQuery;

class MappingRecord extends ActiveRecord
{
    const TABLE = "{{%gathercontent_mapping}}";

    public static function tableName()
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|MappingFieldRecord
     */
    public function getTabs(): ActiveQuery
    {
        return $this->hasMany(MappingTabRecord::className(), ['mappingId' => 'id']);
    }

    /**
     * @return ActiveQuery|MappingRecord
     */
    public function getEntryType(): ActiveQuery
    {
        return $this->hasOne(EntryType::className(), ['id' => 'craftEntryTypeId']);
    }

    /**
     * @return \craft\models\EntryType|null
     */
    public function entryTypeModel()
    {
        /** @var Sections $sectionsService */
        $sectionsService = Craft::$app->get("sections");

        return $sectionsService->getEntryTypeById($this->craftEntryTypeId);
    }

    public function entryTypeUrl()
    {
        $entryType = $this->entryTypeModel();

        return $entryType->getCpEditUrl();
    }
}
